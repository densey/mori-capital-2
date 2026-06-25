<?php
/**
 * Global helpers — small utility functions used across the app.
 */
declare(strict_types=1);

namespace Mori;

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    $base = rtrim((string) Config::get('SITE_URL', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = "{$scheme}://{$host}";
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return '/' . ltrim($path, '/');
}

/**
 * Sanitise a user-provided URL for href attributes. Only http(s) and mailto:
 * are permitted; everything else (including javascript:, data:, vbscript:)
 * collapses to '#'.
 */
function safe_url(?string $url): string
{
    if (!$url) return '#';
    $url = trim($url);
    if ($url === '' || $url === '#') return '#';
    if (preg_match('/^(https?:\/\/|mailto:|tel:)/i', $url)) return $url;
    if (str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '?') || str_starts_with($url, '#')) return $url;
    return '#';
}

function redirect(string $path, int $code = 302): never
{
    header('Location: ' . $path, true, $code);
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) return null;
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $val = $_SESSION['_flash'][$key] ?? null;
    if ($val !== null) unset($_SESSION['_flash'][$key]);
    return $val;
}

function setting(string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $v = Database::instance()->fetchColumn(
            'SELECT setting_value FROM settings WHERE setting_key = :k',
            ['k' => $key]
        );
        return $cache[$key] = ($v !== null ? (string) $v : $default);
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * Locale-aware setting fetcher. Looks up <key>_<locale> first; if missing or
 * empty, falls back to plain <key>; if that is also missing, returns $default.
 * Use for any user-editable copy where DE / EN versions live as two settings.
 */
function setting_i18n(string $key, ?string $default = null): ?string
{
    $loc = I18n::locale();
    $localised = setting($key . '_' . $loc, null);
    if ($localised !== null && $localised !== '') return $localised;
    return setting($key, $default);
}

function format_bytes(int $bytes, int $precision = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = $bytes > 0 ? floor(log($bytes) / log(1024)) : 0;
    $pow = min((int) $pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function format_date(?string $datetime, string $format = 'd M Y'): string
{
    if (!$datetime) return '';
    try {
        return (new \DateTimeImmutable($datetime))->format($format);
    } catch (\Throwable) {
        return '';
    }
}

function slugify(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $tr = [
        'ş'=>'s','Ş'=>'s','ı'=>'i','İ'=>'i','ç'=>'c','Ç'=>'c',
        'ğ'=>'g','Ğ'=>'g','ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o',
        'ä'=>'ae','Ä'=>'ae','ß'=>'ss','é'=>'e','è'=>'e','à'=>'a',
    ];
    $s = strtr($s, $tr);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function current_path(): string
{
    return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
}

function is_active_nav(string $path): bool
{
    $cur = current_path();
    if ($path === '/index.php' || $path === '/') {
        return $cur === '/' || $cur === '/index.php';
    }
    return str_starts_with($cur, $path);
}

function t(string $key, array $params = []): string
{
    return I18n::t($key, $params);
}
