<?php
/**
 * Csrf — per-session CSRF token generator and verifier.
 * Token rotates on each POST request that calls verifyAndRotate().
 */
declare(strict_types=1);

namespace Mori;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        $t = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $t . '">';
    }

    public static function verify(?string $provided): bool
    {
        if ($provided === null || !isset($_SESSION[self::KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::KEY], $provided);
    }

    /** Verify and rotate. Call once per POST. */
    public static function verifyAndRotate(?string $provided): bool
    {
        $ok = self::verify($provided);
        if ($ok) {
            unset($_SESSION[self::KEY]);
            self::token();
        }
        return $ok;
    }

    public static function requireValid(): void
    {
        $provided = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::verify($provided)) {
            http_response_code(419);
            exit('CSRF token invalid or missing.');
        }
    }
}
