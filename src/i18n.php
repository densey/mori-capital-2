<?php
/**
 * i18n — simple key-based translation with EN/DE.
 * Locale is determined by ?lang= query, then cookie, then default from settings.
 */
declare(strict_types=1);

namespace Mori;

final class I18n
{
    private static string $locale = 'en';
    private static array $translations = [];

    public static function init(): void
    {
        $available = ['en', 'de'];
        $default   = 'en';

        // 1. Query param
        if (isset($_GET['lang']) && in_array($_GET['lang'], $available, true)) {
            self::$locale = $_GET['lang'];
            setcookie('mori_lang', self::$locale, time() + 31536000, '/');
        }
        // 2. Cookie
        elseif (isset($_COOKIE['mori_lang']) && in_array($_COOKIE['mori_lang'], $available, true)) {
            self::$locale = $_COOKIE['mori_lang'];
        }
        // 3. Default
        else {
            self::$locale = $default;
        }

        $file = __DIR__ . '/lang/' . self::$locale . '.php';
        if (is_readable($file)) {
            self::$translations = require $file;
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function isLocale(string $loc): bool
    {
        return self::$locale === $loc;
    }

    public static function t(string $key, array $params = []): string
    {
        $value = self::$translations[$key] ?? $key;
        foreach ($params as $k => $v) {
            $value = str_replace(':' . $k, (string) $v, $value);
        }
        return $value;
    }

    /** Picks the right column for current locale: e.g. fieldFor($row, 'name') -> name_en or name_de */
    public static function fieldFor(array $row, string $base): ?string
    {
        $key = $base . '_' . self::$locale;
        if (!empty($row[$key])) return $row[$key];
        // fallback to EN
        $fallback = $base . '_en';
        return $row[$fallback] ?? null;
    }
}
