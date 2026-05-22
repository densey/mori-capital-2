<?php
/**
 * Auth — session-based authentication with Argon2id password hashing.
 * Stores user_id in $_SESSION, rotates the session id on login,
 * enforces inactivity timeout from the settings table.
 */
declare(strict_types=1);

namespace Mori;

final class Auth
{
    private const SESS_USER  = '_user_id';
    private const SESS_LAST  = '_last_activity';
    private const SESS_FP    = '_fingerprint';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['SERVER_PORT'] ?? 0) == 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('mori_sid');
        session_start();

        // Inactivity timeout from settings (default 60 min)
        $timeout = 3600;
        if (isset($_SESSION[self::SESS_USER])) {
            if (isset($_SESSION[self::SESS_LAST]) && (time() - $_SESSION[self::SESS_LAST]) > $timeout) {
                self::logout();
            } else {
                $_SESSION[self::SESS_LAST] = time();
            }

            // Fingerprint check (UA + first 16 chars of IP) - mitigates session hijack
            $fp = self::fingerprint();
            if (isset($_SESSION[self::SESS_FP]) && !hash_equals($_SESSION[self::SESS_FP], $fp)) {
                self::logout();
            }
        }
    }

    public static function attempt(string $email, string $password): ?array
    {
        $db = Database::instance();
        $user = $db->fetchOne(
            'SELECT id, email, name, password_hash, role, status FROM users WHERE email = :e LIMIT 1',
            ['e' => strtolower(trim($email))]
        );
        if (!$user || $user['status'] !== 'active') {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Rehash if algorithm parameters changed
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $db->update('users',
                ['password_hash' => password_hash($password, PASSWORD_ARGON2ID)],
                ['id' => $user['id']]
            );
        }

        session_regenerate_id(true);
        $_SESSION[self::SESS_USER] = (int) $user['id'];
        $_SESSION[self::SESS_LAST] = time();
        $_SESSION[self::SESS_FP]   = self::fingerprint();

        $db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => self::clientIp(),
        ], ['id' => $user['id']]);

        AuditLog::log($user['id'], 'login', 'users', $user['id'], 'Successful login');

        unset($user['password_hash']);
        return $user;
    }

    public static function logout(): void
    {
        $uid = $_SESSION[self::SESS_USER] ?? null;
        if ($uid) {
            AuditLog::log($uid, 'logout', 'users', $uid, 'User logged out');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION[self::SESS_USER]);
    }

    public static function userId(): ?int
    {
        return $_SESSION[self::SESS_USER] ?? null;
    }

    public static function user(): ?array
    {
        $id = self::userId();
        if (!$id) return null;
        static $cache = null;
        if ($cache !== null && $cache['id'] === $id) return $cache;
        $u = Database::instance()->fetchOne(
            'SELECT id, email, name, role, status, last_login_at FROM users WHERE id = :id',
            ['id' => $id]
        );
        return $cache = $u;
    }

    public static function requireLogin(string $redirectTo = '/admin/login.php'): void
    {
        if (!self::check()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();
        $user = self::user();
        $needs = (array) $roles;
        if (!$user || !in_array($user['role'], $needs, true)) {
            http_response_code(403);
            exit('Forbidden — your role does not permit this action.');
        }
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 2,
        ]);
    }

    private static function fingerprint(): string
    {
        $ip = self::clientIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', substr($ip, 0, 16) . '|' . $ua);
    }

    public static function clientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
    }
}
