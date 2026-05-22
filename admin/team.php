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

Auth::requireLogin();
$db = Database::instance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::requireValid();
    $id = (int)$_POST['id'];
    $row = $db->fetchOne('SELECT * FROM team_members WHERE id=:id', ['id'=>$id]);
    if ($row) {
        $db->delete('team_members', ['id' => $id]);
        AuditLog::log(Auth::userId(), 'team_deleted', 'team_members', $id, $row['name']);
        flash('ok', 'Team member deleted.');
    }
    redirect(asset('admin/team.php'));
}

$team = $db->fetchAll('SELECT * FROM team_members ORDER BY display_order');

$adminPage = ['title' => 'Team', 'crumb' => 'Portfolio managers and operations team'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>

<div class="a-card">
    <div class="a-card__head">
        <h2><?= count($team) ?> team members</h2>
        <a class="a-btn" href="<?= asset('admin/team-edit.php') ?>"><i class="fa-solid fa-plus"></i> Add member</a>
    </div>
    <div class="a-card__body" style="padding:0;">
        <table class="a-table">
            <thead><tr><th>Name</th><th>Title (EN)</th><th>Status</th><th>Order</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($team as $m): ?>
                <tr>
                    <td><strong><?= e($m['name']) ?></strong><br><small><?= e($m['email'] ?? '') ?></small></td>
                    <td><?= e($m['title_en']) ?></td>
                    <td><span class="a-badge <?= $m['status']==='active'?'success':'muted' ?>"><?= e($m['status']) ?></span></td>
                    <td><?= e($m['display_order']) ?></td>
                    <td style="text-align:right;">
                        <a class="a-btn ghost sm" href="<?= asset('admin/team-edit.php?id=' . $m['id']) ?>"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete team member?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($m['id']) ?>">
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
