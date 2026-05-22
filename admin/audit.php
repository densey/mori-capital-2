<?php
require __DIR__ . '/../src/bootstrap.php';
use Mori\Auth;
use Mori\AuditLog;
use function Mori\e;
use function Mori\format_date;

Auth::requireRole('super_admin');
$entries = AuditLog::recent(300);

$adminPage = ['title' => 'Audit Log', 'crumb' => 'Last 300 admin actions'];
include __DIR__ . '/partials/layout-start.php';
?>

<div class="a-card">
    <div class="a-card__body" style="padding:0;">
        <table class="a-table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($entries as $a): ?>
                <tr>
                    <td><small><?= e(format_date($a['created_at'], 'd M Y · H:i:s')) ?></small></td>
                    <td><strong><?= e($a['user_name'] ?? 'System') ?></strong><br><small><?= e($a['user_email'] ?? '') ?></small></td>
                    <td><span class="a-badge muted"><?= e(str_replace('_',' ', $a['action'])) ?></span></td>
                    <td><?= e($a['entity'] ?? '') ?><?= $a['entity_id'] ? ' #' . e($a['entity_id']) : '' ?></td>
                    <td><small style="color:var(--a-text-soft);"><?= e($a['details'] ?? '') ?></small></td>
                    <td><small style="font-family:monospace;color:var(--a-muted);"><?= e($a['ip_address']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
