<?php
/**
 * Where a visitor lands after choosing "I am a U.S. person or a UK retail
 * investor — leave the site" in the investor gate when there is no previous
 * page to go back to. Deliberately standalone: no site navigation, no gate.
 */
require __DIR__ . '/src/bootstrap.php';

use function Mori\e;
use function Mori\asset;
use function Mori\t;

header('X-Robots-Tag: noindex, nofollow');
$lang = \Mori\I18n::locale();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(t('gate.restricted_title')) ?> — Mori Capital Management</title>
    <style>
        html, body { margin: 0; min-height: 100%; }
        body {
            background: #0B1F33; color: #33475B;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; box-sizing: border-box;
        }
        .box { background: #fff; border-radius: 12px; max-width: 520px; width: 100%; padding: 36px 32px 28px; box-shadow: 0 30px 80px rgba(0,0,0,.35); text-align: center; }
        .box img { height: 48px; margin-bottom: 22px; }
        h1 { color: #1B3A5C; font-size: 21px; margin: 0 0 14px; }
        p { font-size: 14.5px; line-height: 1.65; margin: 0 0 14px; }
        .msg { background: #FFF6E0; border: 1px solid #F5C76A; color: #6F4F00; border-radius: 8px; padding: 16px 18px; text-align: left; }
        .small { font-size: 12.5px; color: #7A8B99; margin: 22px 0 0; }
        .small a { color: #1B3A5C; }
    </style>
</head>
<body>
    <main class="box">
        <img src="<?= asset('assets/images/mori-capital-logo-dark.fw.png') ?>" alt="Mori Capital Management">
        <h1><?= e(t('gate.restricted_title')) ?></h1>
        <p class="msg"><?= e(t('gate.decline_msg')) ?></p>
        <p class="small"><?= e(t('gate.restricted_mistake')) ?> <a href="<?= e(asset('/') . '?lang=' . $lang) ?>"><?= e(t('gate.restricted_return')) ?></a></p>
    </main>
</body>
</html>
