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

/**
 * Documents shown in the "Fund documentation" teaser on a fund detail page.
 * Filtered by the current locale (a doc's locale must match the active site
 * language, or be 'any') and — once the show_on_fund_page migration is in —
 * limited to the documents the client has explicitly opted in. Falls back to
 * a plain locale filter if that column doesn't exist yet.
 */
function fund_page_documents(int $fundId, int $limit = 12): array
{
    $db  = Database::instance();
    $loc = I18n::locale();

    static $hasFlag = null;
    if ($hasFlag === null) {
        try {
            $hasFlag = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'documents'
                    AND COLUMN_NAME = 'show_on_fund_page'"
            ) > 0;
        } catch (\Throwable) {
            $hasFlag = false;
        }
    }

    $sql = 'SELECT * FROM documents
             WHERE fund_id = :id
               AND (locale = :loc OR locale = "any")'
         . ($hasFlag ? ' AND show_on_fund_page = 1' : '')
         . ' ORDER BY document_date DESC LIMIT ' . max(1, $limit);

    try {
        return $db->fetchAll($sql, ['id' => $fundId, 'loc' => $loc]);
    } catch (\Throwable) {
        return [];
    }
}

/**
 * NAV per share for display: 2–4 decimals (trailing zeros beyond 2 trimmed),
 * locale separators — EN 1,234.5678 · DE 1.234,5678.
 */
function format_nav(float|int|string|null $value, ?string $locale = null): string
{
    if ($value === null || $value === '') return '—';
    $v = (float) $value;
    $decimals = 2;
    foreach ([2, 3, 4] as $d) {
        $decimals = $d;
        if (abs(round($v, $d) - round($v, 4)) < 0.000001) break;
    }
    $de = ($locale ?? I18n::locale()) === 'de';
    return number_format($v, $decimals, $de ? ',' : '.', $de ? '.' : ',');
}

/**
 * Latest stored NAV per share class.
 * @return array<int, array{nav: float, date: string}>  keyed by share_class_id
 */
function latest_navs(): array
{
    try {
        $rows = Database::instance()->fetchAll(
            'SELECT n.share_class_id, n.entry_date, n.nav
               FROM nav_entries n
               JOIN (SELECT share_class_id, MAX(entry_date) AS d FROM nav_entries GROUP BY share_class_id) m
                 ON m.share_class_id = n.share_class_id AND m.d = n.entry_date'
        );
    } catch (\Throwable) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['share_class_id']] = ['nav' => (float) $r['nav'], 'date' => (string) $r['entry_date']];
    }
    return $out;
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
        $out = (new \DateTimeImmutable($datetime))->format($format);
    } catch (\Throwable) {
        return '';
    }
    // PHP's date() only knows English month names. Localise them for non-EN
    // locales so dates like "Oct 1998" / "October 2025" read correctly on /de.
    if (I18n::locale() === 'de') {
        static $de = [
            // full names (F)
            'January' => 'Januar', 'February' => 'Februar', 'March' => 'März',
            'April' => 'April', 'May' => 'Mai', 'June' => 'Juni', 'July' => 'Juli',
            'August' => 'August', 'September' => 'September', 'October' => 'Oktober',
            'November' => 'November', 'December' => 'Dezember',
            // abbreviations (M)
            'Jan' => 'Jan.', 'Feb' => 'Feb.', 'Mar' => 'März', 'Apr' => 'Apr.',
            'Jun' => 'Juni', 'Jul' => 'Juli', 'Aug' => 'Aug.', 'Sep' => 'Sept.',
            'Oct' => 'Okt.', 'Nov' => 'Nov.', 'Dec' => 'Dez.',
            // day names (l) + abbreviations (D)
            'Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch',
            'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 'Saturday' => 'Samstag',
            'Sunday' => 'Sonntag', 'Mon' => 'Mo', 'Tue' => 'Di', 'Wed' => 'Mi',
            'Thu' => 'Do', 'Fri' => 'Fr', 'Sat' => 'Sa', 'Sun' => 'So',
        ];
        // Each maximal alphabetic run is looked up once, so German output is
        // never re-matched (e.g. "June"->"Juni" without the "Jun" rule reapplying).
        $out = preg_replace_callback('/[A-Za-z]+/', fn($m) => $de[$m[0]] ?? $m[0], $out);
    }
    return $out;
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
