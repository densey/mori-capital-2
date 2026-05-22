<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Database;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;

Auth::requireLogin();
$db = Database::instance();
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

<div class="a-card">
    <div class="a-card__head">
        <h2><?= count($subs) ?> subscribers</h2>
        <a class="a-btn ghost" href="?export=1"><i class="fa-solid fa-download"></i> Export CSV</a>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($subs)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No subscribers yet.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>Email</th><th>Language</th><th>Status</th><th>Subscribed</th><th>Confirmed</th></tr></thead>
            <tbody>
                <?php foreach ($subs as $s): ?>
                <tr>
                    <td><strong><?= e($s['email']) ?></strong></td>
                    <td><span class="a-badge muted"><?= e(strtoupper($s['locale'])) ?></span></td>
                    <td><span class="a-badge <?= $s['status']==='confirmed'?'success':($s['status']==='pending'?'warning':'muted') ?>"><?= e($s['status']) ?></span></td>
                    <td><small><?= e(format_date($s['created_at'], 'd M Y H:i')) ?></small></td>
                    <td><small><?= $s['confirmed_at'] ? e(format_date($s['confirmed_at'], 'd M Y H:i')) : '—' ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
