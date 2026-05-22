<?php
use Mori\Auth;
use function Mori\e;

$user = Auth::user();
$initials = '';
if ($user) {
    $parts = preg_split('/\s+/', trim($user['name'] ?? ''));
    foreach ($parts as $p) $initials .= mb_substr($p, 0, 1);
    $initials = mb_strtoupper(mb_substr($initials, 0, 2));
}
$pageTitle = $adminPage['title'] ?? 'Admin';
$pageCrumb = $adminPage['crumb'] ?? '';
?>
<header class="a-topbar">
    <div class="a-topbar__title">
        <h1><?= e($pageTitle) ?></h1>
        <?php if ($pageCrumb): ?><div class="crumb"><?= e($pageCrumb) ?></div><?php endif; ?>
    </div>
    <?php if ($user): ?>
    <div class="a-topbar__user">
        <div class="avatar"><?= e($initials) ?></div>
        <div class="info">
            <div class="name"><?= e($user['name']) ?></div>
            <div class="role"><?= e(str_replace('_', ' ', $user['role'])) ?></div>
        </div>
    </div>
    <?php endif; ?>
</header>
