<?php
/**
 * Config — loads .env (key=value) into immutable static array.
 * Falls back to sensible defaults when keys are missing.
 *
 * Usage:
 *   Config::load(__DIR__.'/../.env');
 *   Config::get('DB_HOST', 'localhost');
 */
declare(strict_types=1);

namespace Mori;

final class Config
{
    private static array $data = [];
    private static bool  $loaded = false;

    public static function load(string $envPath): void
    {
        if (self::$loaded) return;

        if (is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (!str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Strip surrounding quotes
                if (strlen($value) >= 2 && (
                    ($value[0] === '"' && $value[-1] === '"') ||
                    ($value[0] === "'" && $value[-1] === "'")
                )) {
                    $value = substr($value, 1, -1);
                }
                self::$data[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$data);
    }

    public static function all(): array
    {
        return self::$data;
    }
}
