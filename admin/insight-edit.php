<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\slugify;
use function Mori\redirect;

Auth::requireLogin();
$db = Database::instance();
$id = (int)($_GET['id'] ?? 0);
$ins = $id ? $db->fetchOne('SELECT * FROM insights WHERE id=:id', ['id' => $id]) : null;
$isNew = !$ins;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $data = [
        'slug'            => slugify($_POST['slug'] ?? $_POST['title']),
        'locale'          => in_array($_POST['locale'], ['en','de'], true) ? $_POST['locale'] : 'en',
        'category'        => in_array($_POST['category'], ['outlook','factsheet','shareholder_notice','article','press'], true) ? $_POST['category'] : 'article',
        'title'           => trim($_POST['title']),
        'excerpt'         => trim($_POST['excerpt']) ?: null,
        'body'            => $_POST['body'] ?? '',
        'cover_image_path'=> trim($_POST['cover_image_path']) ?: null,
        'publish_date'    => $_POST['publish_date'] ?: date('Y-m-d'),
        'status'          => in_array($_POST['status'], ['draft','published'], true) ? $_POST['status'] : 'draft',
        'created_by'      => Auth::userId(),
    ];
    if ($data['title'] === '') flash('error', 'Title is required.');
    else {
        try {
            if ($isNew) {
                $newId = $db->insert('insights', $data);
                AuditLog::log(Auth::userId(), 'insight_created', 'insights', $newId, $data['title']);
                flash('ok', 'Insight created.');
                redirect(asset('admin/insight-edit.php?id=' . $newId));
            } else {
                $db->update('insights', $data, ['id' => $id]);
                AuditLog::log(Auth::userId(), 'insight_updated', 'insights', $id, $data['title']);
                flash('ok', 'Insight saved.');
                redirect(asset('admin/insight-edit.php?id=' . $id));
            }
        } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    }
}

$adminPage = ['title' => $isNew ? 'New Mori View' : 'Edit Mori View', 'tinymce' => true];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="a-form">
    <?= Csrf::field() ?>
    <div class="a-grid" style="grid-template-columns:2fr 1fr;gap:22px;">
        <div>
            <div class="a-card"><div class="a-card__body">
                <label>Title *</label>
                <input type="text" name="title" required value="<?= e($ins['title'] ?? '') ?>">
                <label>Slug</label>
                <input type="text" name="slug" value="<?= e($ins['slug'] ?? '') ?>" placeholder="auto from title">
                <label>Excerpt (1-2 sentences shown on the homepage and listing)</label>
                <textarea name="excerpt" rows="3"><?= e($ins['excerpt'] ?? '') ?></textarea>
                <label>Body</label>
                <textarea name="body" class="wysiwyg"><?= e($ins['body'] ?? '') ?></textarea>
                <label>Cover image (path or URL)</label>
                <input type="text" name="cover_image_path" value="<?= e($ins['cover_image_path'] ?? '') ?>">
            </div></div>
        </div>
        <aside>
            <div class="a-card"><div class="a-card__head"><h2>Publish</h2></div><div class="a-card__body">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?= ($ins['status'] ?? 'draft')==='draft'?'selected':'' ?>>Draft</option>
                    <option value="published" <?= ($ins['status'] ?? '')==='published'?'selected':'' ?>>Published</option>
                </select>
                <label>Category</label>
                <select name="category">
                    <?php foreach (['outlook'=>'Outlook','factsheet'=>'Factsheet','shareholder_notice'=>'Shareholder Notice','article'=>'Article','press'=>'Press'] as $k=>$lbl): ?>
                    <option value="<?= e($k) ?>" <?= ($ins['category'] ?? 'article')===$k?'selected':'' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Language</label>
                <select name="locale">
                    <option value="en" <?= ($ins['locale'] ?? 'en')==='en'?'selected':'' ?>>English</option>
                    <option value="de" <?= ($ins['locale'] ?? '')==='de'?'selected':'' ?>>Deutsch</option>
                </select>
                <label>Publish date</label>
                <input type="date" name="publish_date" value="<?= e($ins['publish_date'] ?? date('Y-m-d')) ?>">
                <button class="a-btn" type="submit" style="width:100%;margin-top:18px;justify-content:center;"><i class="fa-solid fa-save"></i> Save</button>
                <a class="a-btn ghost" href="<?= asset('admin/insights.php') ?>" style="width:100%;margin-top:8px;justify-content:center;">Cancel</a>
            </div></div>
        </aside>
    </div>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
