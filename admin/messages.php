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
    $action = $_POST['action'] ?? '';
    $row = $db->fetchOne('SELECT * FROM contact_messages WHERE id=:id', ['id' => $id]);
    if ($row) {
        if ($action === 'mark_read') {
            $db->update('contact_messages', ['status' => 'read'], ['id' => $id]);
        } elseif ($action === 'archive') {
            $db->update('contact_messages', ['status' => 'archived'], ['id' => $id]);
        } elseif ($action === 'delete') {
            $db->delete('contact_messages', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'message_deleted', 'contact_messages', $id, $row['email']);
        } elseif ($action === 'reply') {
            $replyBody = trim($_POST['reply_body'] ?? '');
            if ($replyBody === '') {
                flash('error', 'Reply body cannot be empty.');
            } else {
                $user = Auth::user();
                $ok = \Mori\Mail::sendTemplate(
                    $row['email'],
                    'RE: ' . ($row['subject'] ?: 'Your inquiry — Mori Capital'),
                    'Reply from Mori Capital',
                    '<p>' . nl2br(htmlspecialchars($replyBody)) . '</p>'
                    . '<hr style="border:none;border-top:1px solid #E1E7EE;margin:20px 0;">'
                    . '<p style="font-size:13px;color:#7A8B99;"><strong>Your original message:</strong></p>'
                    . '<blockquote style="border-left:3px solid #1ABC9C;padding-left:16px;margin:12px 0;color:#7A8B99;font-size:13px;">'
                    . nl2br(htmlspecialchars($row['message']))
                    . '</blockquote>'
                );
                if ($ok) {
                    $db->update('contact_messages', ['status' => 'read'], ['id' => $id]);
                    AuditLog::log(Auth::userId(), 'message_replied', 'contact_messages', $id, 'Reply to ' . $row['email']);
                    flash('ok', 'Reply sent to ' . $row['email'] . '.');
                } else {
                    flash('error', 'Failed to send reply — check SMTP settings.');
                }
            }
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
                    <td style="text-align:right;white-space:nowrap;">
                        <button class="a-btn sm" onclick='openReply(<?= json_encode($m) ?>)' title="Reply"><i class="fa-solid fa-reply"></i></button>
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

<!-- Reply Modal -->
<div class="a-modal-backdrop" id="replyModal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="a-modal" style="max-width:680px;">
        <div class="a-modal__head">
            <h3><i class="fa-solid fa-reply"></i> Reply to <span id="reply_name"></span></h3>
            <button class="a-modal__close" onclick="this.closest('.a-modal-backdrop').classList.remove('show')">&times;</button>
        </div>
        <div class="a-modal__body">
            <!-- Original message -->
            <div style="background:var(--a-border-soft);border-radius:8px;padding:16px 18px;margin-bottom:18px;">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--a-muted);font-weight:700;margin-bottom:6px;">Original message</div>
                <div style="font-size:13px;color:var(--a-text-soft);"><strong id="reply_subject_display"></strong></div>
                <div id="reply_message_display" style="font-size:13px;color:var(--a-text-soft);margin-top:8px;white-space:pre-wrap;max-height:150px;overflow-y:auto;"></div>
            </div>

            <form method="post" class="a-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="id" id="reply_id" value="">

                <label>Your reply to <strong id="reply_email_display"></strong></label>
                <textarea name="reply_body" rows="8" required placeholder="Type your reply here...

The original message will be included as a quote below your reply."></textarea>
                <div class="hint">Sent as a branded Mori Capital email. The recipient can reply directly to this email.</div>

                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button class="a-btn lg" type="submit" style="flex:1;justify-content:center;"><i class="fa-solid fa-paper-plane"></i> Send reply</button>
                    <button class="a-btn ghost lg" type="button" onclick="this.closest('.a-modal-backdrop').classList.remove('show')" style="justify-content:center;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReply(msg) {
    document.getElementById('reply_id').value = msg.id;
    document.getElementById('reply_name').textContent = msg.name;
    document.getElementById('reply_email_display').textContent = msg.email;
    document.getElementById('reply_subject_display').textContent = msg.subject || '(no subject)';
    document.getElementById('reply_message_display').textContent = msg.message;
    document.getElementById('replyModal').classList.add('show');
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
