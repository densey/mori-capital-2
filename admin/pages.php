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

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::requireValid();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $page = Database::instance()->fetchOne('SELECT * FROM pages WHERE id=:id', ['id'=>$id]);
        if ($page) {
            Database::instance()->delete('pages', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'page_deleted', 'pages', $id, $page['slug'] . ' (' . $page['locale'] . ')');
            flash('ok', 'Page deleted.');
        }
    }
    redirect(asset('admin/pages.php'));
}

$db = Database::instance();
$loc = $_GET['lang'] ?? 'all';
$where = []; $params = [];
if ($loc === 'en' || $loc === 'de') { $where[] = 'locale = :loc'; $params['loc'] = $loc; }
if (!empty($_GET['q'])) { $where[] = '(title LIKE :q OR slug LIKE :q)'; $params['q'] = '%' . $_GET['q'] . '%'; }
$sql = 'SELECT * FROM pages' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY slug, locale';
$pages = $db->fetchAll($sql, $params);

$adminPage = ['title' => 'Pages', 'crumb' => 'Manage public website pages — EN and DE'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><i class="fa-solid fa-circle-check"></i> <?= e($ok) ?></div><?php endif; ?>

<div class="a-card">
    <div class="a-card__head">
        <div style="display:flex;gap:10px;align-items:center;">
            <a class="a-btn ghost sm" href="<?= asset('admin/pages.php') ?>" <?= $loc==='all'?'style="background:var(--a-border-soft);color:var(--a-navy);"':'' ?>>All</a>
            <a class="a-btn ghost sm" href="<?= asset('admin/pages.php?lang=en') ?>" <?= $loc==='en'?'style="background:var(--a-border-soft);color:var(--a-navy);"':'' ?>>English</a>
            <a class="a-btn ghost sm" href="<?= asset('admin/pages.php?lang=de') ?>" <?= $loc==='de'?'style="background:var(--a-border-soft);color:var(--a-navy);"':'' ?>>German</a>
            <form method="get" style="display:flex;gap:6px;margin-left:14px;">
                <?php if ($loc!=='all'): ?><input type="hidden" name="lang" value="<?= e($loc) ?>"><?php endif; ?>
                <input type="text" name="q" placeholder="Search…" value="<?= e($_GET['q'] ?? '') ?>" class="input" style="font-size:13px;padding:7px 11px;">
            </form>
        </div>
        <div style="display:flex;gap:8px;">
            <a class="a-btn" href="<?= asset('admin/page-edit.php') ?>"><i class="fa-solid fa-plus"></i> New page (TinyMCE)</a>
            <a class="a-btn" href="<?= asset('admin/page-builder.php') ?>" style="background:#0E1F36;border-color:#0E1F36;"><i class="fa-solid fa-cubes"></i> Block Builder</a>
            <a class="a-btn" href="<?= asset('admin/page-builder-grapes.php') ?>" style="background:#1B3A5C;border-color:#1B3A5C;"><i class="fa-solid fa-wand-magic-sparkles"></i> GrapesJS</a>
        </div>
    </div>
    <div class="a-card__body" style="padding:0;">
        <table class="a-table">
            <thead>
                <tr><th>Title</th><th>Slug</th><th>Lang</th><th>Status</th><th>Updated</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($pages)): ?>
                <tr><td colspan="6" style="padding:30px;text-align:center;color:var(--a-muted);">No pages.</td></tr>
                <?php else: foreach ($pages as $p): ?>
                <tr>
                    <td><strong><?= e($p['title']) ?></strong></td>
                    <td><code style="background:var(--a-border-soft);padding:1px 6px;border-radius:3px;font-size:12px;">/<?= e($p['slug']) ?></code></td>
                    <td><span class="a-badge muted"><?= e(strtoupper($p['locale'])) ?></span></td>
                    <td><span class="a-badge <?= $p['status']==='published'?'success':'warning' ?>"><?= e($p['status']) ?></span></td>
                    <td><small><?= e(format_date($p['updated_at'], 'd M Y H:i')) ?></small></td>
                    <td style="text-align:right;">
                        <a class="a-btn ghost sm" href="<?= asset($p['slug']) ?>.php" target="_blank" rel="noopener noreferrer" title="Preview"><i class="fa-solid fa-eye"></i></a>
                        <a class="a-btn ghost sm" href="<?= asset('admin/page-builder.php?id=' . $p['id']) ?>" title="Block Builder"><i class="fa-solid fa-cubes"></i></a>
                        <a class="a-btn ghost sm" href="<?= asset('admin/page-builder-grapes.php?id=' . $p['id']) ?>" title="GrapesJS Editor"><i class="fa-solid fa-wand-magic-sparkles"></i></a>
                        <a class="a-btn ghost sm" href="<?= asset('admin/page-edit.php?id=' . $p['id']) ?>" title="Classic editor (TinyMCE)"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this page?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                            <button class="a-btn danger sm" type="submit"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
