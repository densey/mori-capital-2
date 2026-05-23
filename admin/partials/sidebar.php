<?php
use Mori\Auth;
use function Mori\e;
use function Mori\asset;
use function Mori\is_active_nav;

$user = Auth::user();
?>
<aside class="a-sidebar">
    <div class="a-sidebar__brand">
        <img src="<?= asset('assets/images/android-icon-192x192.png') ?>" alt="Mori" style="border-radius:50%;">
        <div class="meta">
            <span class="title">Mori CMS</span>
            <span class="tag">Admin Panel</span>
        </div>
    </div>

    <ul class="a-nav">
        <li class="a-nav__section">Overview</li>
        <li><a href="<?= asset('admin/dashboard.php') ?>" class="<?= is_active_nav('/admin/dashboard.php')?'active':'' ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>

        <li class="a-nav__section">Content</li>
        <li><a href="<?= asset('admin/pages.php') ?>" class="<?= is_active_nav('/admin/pages.php')||is_active_nav('/admin/page-edit.php')?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Pages</a></li>
        <li><a href="<?= asset('admin/page-builder.php') ?>" class="<?= is_active_nav('/admin/page-builder.php')?'active':'' ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> Visual Builder</a></li>
        <li><a href="<?= asset('admin/insights.php') ?>" class="<?= is_active_nav('/admin/insights.php')||is_active_nav('/admin/insight-edit.php')?'active':'' ?>"><i class="fa-solid fa-newspaper"></i> Mori Views</a></li>
        <li><a href="<?= asset('admin/team.php') ?>" class="<?= is_active_nav('/admin/team.php')||is_active_nav('/admin/team-edit.php')?'active':'' ?>"><i class="fa-solid fa-users"></i> Team</a></li>

        <li class="a-nav__section">Funds</li>
        <li><a href="<?= asset('admin/funds.php') ?>" class="<?= is_active_nav('/admin/funds.php')||is_active_nav('/admin/fund-edit.php')?'active':'' ?>"><i class="fa-solid fa-chart-pie"></i> Funds &amp; Share Classes</a></li>
        <li><a href="<?= asset('admin/performance.php') ?>" class="<?= is_active_nav('/admin/performance.php')?'active':'' ?>"><i class="fa-solid fa-chart-line"></i> Performance (NAV)</a></li>
        <li><a href="<?= asset('admin/documents.php') ?>" class="<?= is_active_nav('/admin/documents.php')?'active':'' ?>"><i class="fa-regular fa-folder-open"></i> Documents</a></li>

        <li class="a-nav__section">Engagement</li>
        <li><a href="<?= asset('admin/messages.php') ?>" class="<?= is_active_nav('/admin/messages.php')?'active':'' ?>"><i class="fa-regular fa-envelope"></i> Contact Messages</a></li>
        <li><a href="<?= asset('admin/newsletter.php') ?>" class="<?= is_active_nav('/admin/newsletter.php')?'active':'' ?>"><i class="fa-regular fa-paper-plane"></i> Newsletter</a></li>

        <li class="a-nav__section">System</li>
        <li><a href="<?= asset('admin/media.php') ?>" class="<?= is_active_nav('/admin/media.php')?'active':'' ?>"><i class="fa-regular fa-images"></i> Media Library</a></li>
        <li><a href="<?= asset('admin/seo.php') ?>" class="<?= is_active_nav('/admin/seo.php')?'active':'' ?>"><i class="fa-solid fa-magnifying-glass-chart"></i> SEO &amp; Custom Code</a></li>
        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
        <li><a href="<?= asset('admin/users.php') ?>" class="<?= is_active_nav('/admin/users.php')?'active':'' ?>"><i class="fa-solid fa-user-shield"></i> Users</a></li>
        <li><a href="<?= asset('admin/settings.php') ?>" class="<?= is_active_nav('/admin/settings.php')?'active':'' ?>"><i class="fa-solid fa-gear"></i> Settings</a></li>
        <li><a href="<?= asset('admin/database.php') ?>" class="<?= is_active_nav('/admin/database.php')?'active':'' ?>"><i class="fa-solid fa-database"></i> Database &amp; Migrations</a></li>
        <li><a href="<?= asset('admin/audit.php') ?>" class="<?= is_active_nav('/admin/audit.php')?'active':'' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Audit Log</a></li>
        <?php endif; ?>

        <li class="a-nav__section">Site</li>
        <li><a href="<?= asset('/') ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square"></i> View Site</a></li>
        <li><a href="<?= asset('admin/logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i> Log out</a></li>
    </ul>
</aside>
