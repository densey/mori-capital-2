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
    <button type="button" class="a-menu-toggle" id="aMenuToggle" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
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

<script>
// Mobile off-canvas sidebar toggle
(function () {
    var toggle = document.getElementById('aMenuToggle');
    var sidebar = document.querySelector('.a-sidebar');
    if (!toggle || !sidebar) return;
    var overlay = document.createElement('div');
    overlay.className = 'a-nav-overlay';
    document.body.appendChild(overlay);
    function open()  { sidebar.classList.add('open');  overlay.classList.add('open');  document.body.classList.add('a-nav-open'); }
    function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.classList.remove('a-nav-open'); }
    toggle.addEventListener('click', function () { sidebar.classList.contains('open') ? close() : open(); });
    overlay.addEventListener('click', close);
    // Close when a nav link is tapped (so navigation feels natural on mobile)
    sidebar.querySelectorAll('.a-nav a').forEach(function (a) { a.addEventListener('click', close); });
    // Reset when resizing back to desktop
    window.addEventListener('resize', function () { if (window.innerWidth > 900) close(); });
})();
</script>
