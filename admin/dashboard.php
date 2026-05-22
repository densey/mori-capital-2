<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\AuditLog;
use Mori\Database;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;

Auth::requireLogin();
$db = Database::instance();

// Stats
try {
    $stats = [
        'pages'    => (int) $db->fetchColumn('SELECT COUNT(*) FROM pages'),
        'docs'     => (int) $db->fetchColumn('SELECT COUNT(*) FROM documents'),
        'insights' => (int) $db->fetchColumn('SELECT COUNT(*) FROM insights'),
        'msgs_new' => (int) $db->fetchColumn('SELECT COUNT(*) FROM contact_messages WHERE status="new"'),
        'subs'     => (int) $db->fetchColumn('SELECT COUNT(*) FROM newsletter_subscribers WHERE status<>"unsubscribed"'),
        'users'    => (int) $db->fetchColumn('SELECT COUNT(*) FROM users WHERE status="active"'),
    ];
    $recentMsgs = $db->fetchAll('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5');
    $recentActions = AuditLog::recent(10);
} catch (\Throwable) {
    $stats = []; $recentMsgs = []; $recentActions = [];
}

$adminPage = ['title' => 'Dashboard', 'crumb' => 'Overview of content, documents and activity'];
include __DIR__ . '/partials/layout-start.php';
$user = Auth::user();
?>

<div class="a-alert info" style="margin-bottom:24px;">
    <strong>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?>.</strong> Last login: <?= e(format_date($user['last_login_at'] ?? '', 'd M Y · H:i') ?: '—') ?>
</div>

<div class="a-grid a-grid-3" style="margin-bottom:24px;">
    <div class="a-stat">
        <div class="a-stat__icon navy"><i class="fa-solid fa-file-lines"></i></div>
        <div><div class="a-stat__lbl">Pages</div><div class="a-stat__val"><?= e($stats['pages'] ?? 0) ?></div></div>
    </div>
    <div class="a-stat">
        <div class="a-stat__icon"><i class="fa-regular fa-folder-open"></i></div>
        <div><div class="a-stat__lbl">Documents</div><div class="a-stat__val"><?= e($stats['docs'] ?? 0) ?></div></div>
    </div>
    <div class="a-stat">
        <div class="a-stat__icon navy"><i class="fa-solid fa-newspaper"></i></div>
        <div><div class="a-stat__lbl">Mori Views</div><div class="a-stat__val"><?= e($stats['insights'] ?? 0) ?></div></div>
    </div>
    <div class="a-stat">
        <div class="a-stat__icon"><i class="fa-regular fa-envelope"></i></div>
        <div><div class="a-stat__lbl">New messages</div><div class="a-stat__val"><?= e($stats['msgs_new'] ?? 0) ?></div></div>
    </div>
    <div class="a-stat">
        <div class="a-stat__icon navy"><i class="fa-regular fa-paper-plane"></i></div>
        <div><div class="a-stat__lbl">Subscribers</div><div class="a-stat__val"><?= e($stats['subs'] ?? 0) ?></div></div>
    </div>
    <div class="a-stat">
        <div class="a-stat__icon"><i class="fa-solid fa-user-shield"></i></div>
        <div><div class="a-stat__lbl">Admin users</div><div class="a-stat__val"><?= e($stats['users'] ?? 0) ?></div></div>
    </div>
</div>

<!-- Quick actions -->
<div class="a-card" style="margin-bottom:24px;">
    <div class="a-card__head"><h2>Quick actions</h2></div>
    <div class="a-card__body" style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="a-btn" href="<?= asset('admin/page-edit.php') ?>"><i class="fa-solid fa-plus"></i> New page</a>
        <a class="a-btn" href="<?= asset('admin/insight-edit.php') ?>"><i class="fa-solid fa-plus"></i> New Mori View</a>
        <a class="a-btn ghost" href="<?= asset('admin/documents.php?action=upload') ?>"><i class="fa-solid fa-upload"></i> Upload document</a>
        <a class="a-btn ghost" href="<?= asset('admin/performance.php') ?>"><i class="fa-solid fa-chart-line"></i> Enter NAV</a>
        <a class="a-btn ghost" href="<?= asset('admin/media.php') ?>"><i class="fa-regular fa-images"></i> Media library</a>
    </div>
</div>

<div class="a-grid a-grid-2">
    <!-- Recent messages -->
    <div class="a-card">
        <div class="a-card__head">
            <h2>Recent contact messages</h2>
            <a class="a-btn ghost sm" href="<?= asset('admin/messages.php') ?>">View all</a>
        </div>
        <div class="a-card__body" style="padding:0;">
            <?php if (empty($recentMsgs)): ?>
                <div style="padding:30px;text-align:center;color:var(--a-muted);">No messages yet.</div>
            <?php else: ?>
            <table class="a-table">
                <thead><tr><th>From</th><th>Subject</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recentMsgs as $m): ?>
                <tr>
                    <td><strong><?= e($m['name']) ?></strong><br><small><?= e($m['email']) ?></small></td>
                    <td><?= e(mb_substr($m['subject'] ?: $m['message'], 0, 60)) ?>…</td>
                    <td><small><?= e(format_date($m['created_at'], 'd M Y · H:i')) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="a-card">
        <div class="a-card__head">
            <h2>Recent activity</h2>
            <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                <a class="a-btn ghost sm" href="<?= asset('admin/audit.php') ?>">Audit log</a>
            <?php endif; ?>
        </div>
        <div class="a-card__body" style="padding:0;">
            <?php if (empty($recentActions)): ?>
                <div style="padding:30px;text-align:center;color:var(--a-muted);">No activity yet.</div>
            <?php else: ?>
            <table class="a-table">
                <thead><tr><th>User</th><th>Action</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recentActions as $a): ?>
                <tr>
                    <td><strong><?= e($a['user_name'] ?? 'System') ?></strong></td>
                    <td><span class="a-badge muted"><?= e(str_replace('_',' ', $a['action'])) ?></span> <?= e($a['entity'] ? $a['entity'] : '') ?></td>
                    <td><small><?= e(format_date($a['created_at'], 'd M · H:i')) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
