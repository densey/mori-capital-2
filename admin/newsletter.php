<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\redirect;
use function Mori\format_date;

Auth::requireLogin();
$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::requireValid();
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId) {
        $sub = $db->fetchOne('SELECT email FROM newsletter_subscribers WHERE id = :id', ['id' => $delId]);
        $db->delete('newsletter_subscribers', ['id' => $delId]);
        AuditLog::log(Auth::userId(), 'subscriber_deleted', 'newsletter_subscribers', $delId, $sub['email'] ?? '');
        flash('ok', 'Subscriber removed.');
    }
    redirect(asset('admin/newsletter.php'));
}

$subs = $db->fetchAll('SELECT * FROM newsletter_subscribers ORDER BY created_at DESC');

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="mori-newsletter-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email','locale','status','created_at','confirmed_at']);
    foreach ($subs as $s) fputcsv($out, [$s['email'], $s['locale'], $s['status'], $s['created_at'], $s['confirmed_at']]);
    fclose($out);
    exit;
}

$adminPage = ['title' => 'Newsletter', 'crumb' => 'Subscribers to the EEMEA market commentary list'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>

<div class="a-card">
    <div class="a-card__head">
        <h2><?= count($subs) ?> subscribers</h2>
        <div style="display:flex;gap:8px;">
            <a class="a-btn ghost" href="<?= asset('admin/newsletter-send.php') ?>"><i class="fa-solid fa-paper-plane"></i> Send Newsletter</a>
            <a class="a-btn ghost" href="?export=1"><i class="fa-solid fa-download"></i> Export CSV</a>
        </div>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($subs)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No subscribers yet.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>Email</th><th>Language</th><th>Status</th><th>Subscribed</th><th>Confirmed</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($subs as $s): ?>
                <tr>
                    <td><strong><?= e($s['email']) ?></strong></td>
                    <td><span class="a-badge muted"><?= e(strtoupper($s['locale'])) ?></span></td>
                    <td><span class="a-badge <?= $s['status']==='confirmed'?'success':($s['status']==='pending'?'warning':'muted') ?>"><?= e($s['status']) ?></span></td>
                    <td><small><?= e(format_date($s['created_at'], 'd M Y H:i')) ?></small></td>
                    <td><small><?= $s['confirmed_at'] ? e(format_date($s['confirmed_at'], 'd M Y H:i')) : '—' ?></small></td>
                    <td style="text-align:right;">
                        <form method="post" style="display:inline;" onsubmit="return confirm('Remove this subscriber?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                            <button class="a-btn danger sm" type="submit" title="Remove"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
