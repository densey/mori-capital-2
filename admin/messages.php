<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\format_date;
use function Mori\redirect;

Auth::requireLogin();
$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    $row = $db->fetchOne('SELECT * FROM contact_messages WHERE id=:id', ['id' => $id]);
    if ($row) {
        if ($action === 'mark_read') {
            $db->update('contact_messages', ['status' => 'read'], ['id' => $id]);
        } elseif ($action === 'archive') {
            $db->update('contact_messages', ['status' => 'archived'], ['id' => $id]);
        } elseif ($action === 'delete') {
            $db->delete('contact_messages', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'message_deleted', 'contact_messages', $id, $row['email']);
        }
    }
    redirect(asset('admin/messages.php'));
}

$filter = $_GET['filter'] ?? 'all';
$where = []; $params = [];
if (in_array($filter, ['new','read','archived'], true)) {
    $where[] = 'status = :s'; $params['s'] = $filter;
}
$messages = $db->fetchAll('SELECT * FROM contact_messages' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC', $params);

$adminPage = ['title' => 'Contact Messages', 'crumb' => 'Inquiries submitted through the contact form'];
include __DIR__ . '/partials/layout-start.php';
?>

<div class="a-card">
    <div class="a-card__head">
        <div style="display:flex;gap:8px;">
            <?php foreach (['all'=>'All','new'=>'New','read'=>'Read','archived'=>'Archived'] as $k=>$lbl): ?>
            <a class="a-btn ghost sm" href="?filter=<?= $k ?>" <?= $filter===$k?'style="background:var(--a-border-soft);color:var(--a-navy);"':'' ?>><?= $lbl ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($messages)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No messages.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>From</th><th>Subject / Message</th><th>When</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($messages as $m): ?>
                <tr>
                    <td><strong><?= e($m['name']) ?></strong><br><a href="mailto:<?= e($m['email']) ?>" style="font-size:12px;"><?= e($m['email']) ?></a></td>
                    <td><strong style="display:block;margin-bottom:4px;"><?= e($m['subject'] ?: '(no subject)') ?></strong><small style="color:var(--a-text-soft);"><?= e(mb_substr($m['message'], 0, 200)) ?><?= mb_strlen($m['message']) > 200?'…':'' ?></small></td>
                    <td><small><?= e(format_date($m['created_at'], 'd M Y · H:i')) ?></small><br><small style="color:var(--a-muted);font-size:11px;"><?= e($m['ip_address']) ?></small></td>
                    <td><span class="a-badge <?= $m['status']==='new'?'info':($m['status']==='read'?'muted':'warning') ?>"><?= e($m['status']) ?></span></td>
                    <td style="text-align:right;">
                        <?php if ($m['status'] === 'new'): ?>
                        <form method="post" style="display:inline;"><?= Csrf::field() ?><input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="<?= e($m['id']) ?>"><button class="a-btn ghost sm"><i class="fa-regular fa-envelope-open"></i></button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline;"><?= Csrf::field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= e($m['id']) ?>"><button class="a-btn ghost sm" title="Archive"><i class="fa-solid fa-box-archive"></i></button></form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete message?');"><?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($m['id']) ?>"><button class="a-btn danger sm"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
