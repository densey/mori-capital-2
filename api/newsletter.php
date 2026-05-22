<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Csrf;
use Mori\Database;
use Mori\I18n;
use function Mori\redirect;
use function Mori\flash;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/');

$ref = $_SERVER['HTTP_REFERER'] ?? '/';

if (!Csrf::verify($_POST['_csrf'] ?? null)) {
    flash('newsletter_error', 'Invalid request — please try again.');
    redirect($ref);
}

$email = strtolower(trim($_POST['mail'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('newsletter_error', 'Please enter a valid email address.');
    redirect($ref);
}

try {
    $db = Database::instance();
    $existing = $db->fetchOne('SELECT id, status FROM newsletter_subscribers WHERE email = :e', ['e' => $email]);
    if ($existing) {
        if ($existing['status'] === 'unsubscribed') {
            $db->update('newsletter_subscribers', ['status' => 'pending'], ['id' => $existing['id']]);
        }
        flash('newsletter_ok', 'Thank you — your subscription has been recorded.');
    } else {
        $db->insert('newsletter_subscribers', [
            'email'         => $email,
            'locale'        => I18n::locale(),
            'status'        => 'pending',
            'confirm_token' => bin2hex(random_bytes(24)),
        ]);
        flash('newsletter_ok', 'Thank you — please check your inbox to confirm your subscription.');
    }
} catch (\Throwable $e) {
    flash('newsletter_error', 'Something went wrong — please try again later.');
}

redirect($ref);
