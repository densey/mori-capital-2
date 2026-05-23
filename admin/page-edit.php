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
$page = $id ? $db->fetchOne('SELECT * FROM pages WHERE id = :id', ['id' => $id]) : null;
$isNew = !$page;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $data = [
        'slug'             => slugify($_POST['slug'] ?? '') ?: slugify($_POST['title'] ?? ''),
        'locale'           => in_array($_POST['locale'] ?? 'en', ['en','de'], true) ? $_POST['locale'] : 'en',
        'title'            => trim($_POST['title'] ?? ''),
        'meta_title'       => trim($_POST['meta_title'] ?? '') ?: null,
        'meta_description' => trim($_POST['meta_description'] ?? '') ?: null,
        'body'             => $_POST['body'] ?? '',
        'status'           => in_array($_POST['status'] ?? 'draft', ['draft','published'], true) ? $_POST['status'] : 'draft',
        'updated_by'       => Auth::userId(),
    ];
    if ($data['title'] === '' || $data['slug'] === '') {
        flash('error', 'Title and slug are required.');
    } else {
        try {
            if ($isNew) {
                $newId = $db->insert('pages', $data);
                AuditLog::log(Auth::userId(), 'page_created', 'pages', $newId, $data['slug'] . ' (' . $data['locale'] . ')');
                flash('ok', 'Page created.');
                redirect(asset('admin/page-edit.php?id=' . $newId));
            } else {
                $db->update('pages', $data, ['id' => $id]);
                AuditLog::log(Auth::userId(), 'page_updated', 'pages', $id, $data['slug'] . ' (' . $data['locale'] . ')');
                flash('ok', 'Page saved.');
                redirect(asset('admin/page-edit.php?id=' . $id));
            }
        } catch (\PDOException $e) {
            flash('error', 'Could not save (duplicate slug + locale?): ' . $e->getMessage());
        }
    }
}

$adminPage = [
    'title' => $isNew ? 'New page' : 'Edit page',
    'crumb' => $isNew ? 'Create a new public page' : ('Editing ' . ($page['slug'] ?? '')),
    'tinymce' => true,
];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><i class="fa-solid fa-circle-check"></i> <?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($err) ?></div><?php endif; ?>

<form method="post" class="a-form">
    <?= Csrf::field() ?>

    <div class="a-grid" style="grid-template-columns:2fr 1fr;gap:24px;">
        <div>
            <div class="a-card">
                <div class="a-card__body">
                    <label>Page title *</label>
                    <input type="text" name="title" required value="<?= e($page['title'] ?? '') ?>">

                    <label>URL slug *</label>
                    <input type="text" name="slug" required value="<?= e($page['slug'] ?? '') ?>" placeholder="e.g. about, investment-style">
                    <div class="hint">Lowercase, hyphenated. URL: /<?= e($page['slug'] ?? 'your-slug') ?>.php</div>

                    <label>Content (WYSIWYG)</label>
                    <textarea name="body" class="wysiwyg"><?= e($page['body'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="a-card" style="margin-top:18px;">
                <div class="a-card__head"><h2>SEO</h2></div>
                <div class="a-card__body">
                    <label>Meta title (browser tab + Google)</label>
                    <input type="text" name="meta_title" maxlength="160" value="<?= e($page['meta_title'] ?? '') ?>">

                    <label>Meta description</label>
                    <textarea name="meta_description" rows="3" maxlength="320"><?= e($page['meta_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <aside>
            <div class="a-card">
                <div class="a-card__head"><h2>Publish</h2></div>
                <div class="a-card__body">
                    <label>Status</label>
                    <select name="status">
                        <option value="draft"     <?= ($page['status'] ?? 'draft')==='draft'?'selected':'' ?>>Draft</option>
                        <option value="published" <?= ($page['status'] ?? '')==='published'?'selected':'' ?>>Published</option>
                    </select>

                    <label>Language</label>
                    <select name="locale">
                        <option value="en" <?= ($page['locale'] ?? 'en')==='en'?'selected':'' ?>>English</option>
                        <option value="de" <?= ($page['locale'] ?? '')==='de'?'selected':'' ?>>Deutsch</option>
                    </select>

                    <button class="a-btn" type="submit" style="width:100%;margin-top:18px;justify-content:center;"><i class="fa-solid fa-save"></i> Save page</button>
                    <a class="a-btn ghost" href="<?= asset('admin/pages.php') ?>" style="width:100%;margin-top:8px;justify-content:center;">Cancel</a>
                    <?php if (!$isNew): ?>
                    <a class="a-btn ghost" href="<?= asset('admin/page-builder.php?id=' . (int)$page['id']) ?>" style="width:100%;margin-top:8px;justify-content:center;background:#0E1F36;color:#fff;border-color:#0E1F36;"><i class="fa-solid fa-wand-magic-sparkles"></i> Open in Visual Builder</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$isNew): ?>
            <div class="a-card" style="margin-top:18px;">
                <div class="a-card__head"><h2>Info</h2></div>
                <div class="a-card__body" style="font-size:12.5px;color:var(--a-muted);">
                    <div><strong>Created:</strong> <?= e(\Mori\format_date($page['created_at'], 'd M Y H:i')) ?></div>
                    <div><strong>Updated:</strong> <?= e(\Mori\format_date($page['updated_at'], 'd M Y H:i')) ?></div>
                    <hr style="border:none;border-top:1px solid var(--a-border);margin:12px 0;">
                    <a href="<?= asset($page['slug']) ?>.php" target="_blank" rel="noopener noreferrer" style="font-size:13px;"><i class="fa-solid fa-arrow-up-right-from-square"></i> View live</a>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
