<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\redirect;

// Already logged in — go to dashboard
if (Auth::check()) redirect(asset('admin/dashboard.php'));

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request — please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        // Simple rate-limit via session
        $_SESSION['_login_attempts'] = ($_SESSION['_login_attempts'] ?? 0) + 1;
        if ($_SESSION['_login_attempts'] > 8) {
            $error = 'Too many login attempts. Please try again later.';
        } else {
            $user = Auth::attempt($email, $pass);
            if ($user) {
                $_SESSION['_login_attempts'] = 0;
                redirect(asset('admin/dashboard.php'));
            } else {
                $error = 'Invalid email or password.';
                \Mori\AuditLog::log(null, 'login_failed', 'users', null, 'Email: ' . $email);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login — Mori Capital CMS</title>
<link rel="icon" type="image/png" sizes="192x192" href="<?= asset('assets/images/android-icon-192x192.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com/">
<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= asset('admin/assets/admin.css') ?>">
</head>
<body>

<div class="a-login">
    <div class="a-login__panel">
        <img class="a-login__logo" src="<?= asset('assets/images/mori-capital-logo-dark.fw.png') ?>" alt="Mori Capital">
        <h1>Sign in to the CMS</h1>
        <div class="sub">Mori Capital Management — admin panel</div>

        <?php if ($error): ?>
            <div class="a-alert error"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="a-form">
            <?= Csrf::field() ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button type="submit" class="a-btn lg" style="width:100%;margin-top:22px;justify-content:center;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign in
            </button>
        </form>

        <div style="text-align:center;margin-top:22px;font-size:12px;color:var(--a-muted);">
            <a href="<?= asset('/') ?>" style="color:var(--a-muted);">← Back to the website</a>
        </div>
    </div>
</div>

</body>
</html>
