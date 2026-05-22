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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::requireValid();
    $id = (int)$_POST['id'];
    $row = $db->fetchOne('SELECT * FROM insights WHERE id=:id', ['id'=>$id]);
    if ($row) {
        $db->delete('insights', ['id' => $id]);
        AuditLog::log(Auth::userId(), 'insight_deleted', 'insights', $id, $row['title']);
        flash('ok', 'Insight deleted.');
    }
    redirect(asset('admin/insights.php'));
}

$insights = $db->fetchAll('SELECT * FROM insights ORDER BY publish_date DESC, created_at DESC');

$adminPage = ['title' => 'Mori Views', 'crumb' => 'Outlooks, factsheets, shareholder notices, articles'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>

<div class="a-card">
    <div class="a-card__head">
        <h2><?= count($insights) ?> entries</h2>
        <a class="a-btn" href="<?= asset('admin/insight-edit.php') ?>"><i class="fa-solid fa-plus"></i> New insight</a>
    </div>
    <div class="a-card__body" style="padding:0;">
        <table class="a-table">
            <thead><tr><th>Title</th><th>Category</th><th>Lang</th><th>Published</th><th>Status</th><th>Views</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($insights as $i): ?>
                <tr>
                    <td><strong><?= e($i['title']) ?></strong><br><small style="font-family:monospace;color:var(--a-muted);">/<?= e($i['slug']) ?></small></td>
                    <td><span class="a-badge muted"><?= e(str_replace('_',' ',$i['category'])) ?></span></td>
                    <td><span class="a-badge muted"><?= e(strtoupper($i['locale'])) ?></span></td>
                    <td><small><?= e(format_date($i['publish_date'])) ?></small></td>
                    <td><span class="a-badge <?= $i['status']==='published'?'success':'warning' ?>"><?= e($i['status']) ?></span></td>
                    <td><?= e($i['view_count']) ?></td>
                    <td style="text-align:right;">
                        <a class="a-btn ghost sm" href="<?= asset('insight.php?slug=' . e($i['slug'])) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-eye"></i></a>
                        <a class="a-btn ghost sm" href="<?= asset('admin/insight-edit.php?id=' . $i['id']) ?>"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($i['id']) ?>">
                            <button class="a-btn danger sm" type="submit"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
