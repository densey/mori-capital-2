<?php
/**
 * Bootstrap — single entry point for all PHP files.
 * Loads config, helpers, starts session, initialises i18n.
 *
 * Usage at top of every public/admin PHP file:
 *   require __DIR__ . '/../src/bootstrap.php';   // adjust depth
 */
declare(strict_types=1);

// Strict error reporting (production: log only, no display)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// PSR-4-like very small autoloader for the Mori namespace
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Mori\\')) return;
    $relative = str_replace('Mori\\', '', $class);
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) require $file;
});

require __DIR__ . '/helpers.php';

// Load config from .env at project root (one level above /src)
\Mori\Config::load(dirname(__DIR__) . '/.env');

// Display errors in development
if (\Mori\Config::get('APP_ENV', 'production') === 'development') {
    ini_set('display_errors', '1');
}

// Sessions + auth
\Mori\Auth::start();

// i18n
\Mori\I18n::init();

date_default_timezone_set((string) \Mori\Config::get('APP_TZ', 'Europe/Malta'));
