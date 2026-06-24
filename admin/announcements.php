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
use function Mori\slugify;
use function Mori\format_date;

Auth::requireLogin();
$db = Database::instance();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->delete('fund_announcements', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'announcement_deleted', 'fund_announcements', $id);
            flash('ok', 'Announcement deleted.');
        }
        redirect(asset('admin/announcements.php'));
    }

    if ($action === 'save') {
        $id = (int)($_POST['ann_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Title is required.');
            redirect(asset('admin/announcements.php'));
        }
        $slug = slugify($_POST['slug'] ?? '') ?: slugify($title);
        // Avoid slug collision
        if ($id) {
            $exists = $db->fetchColumn('SELECT id FROM fund_announcements WHERE slug = :s AND id != :i', ['s' => $slug, 'i' => $id]);
        } else {
            $exists = $db->fetchColumn('SELECT id FROM fund_announcements WHERE slug = :s', ['s' => $slug]);
        }
        if ($exists) $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        $data = [
            'fund_id'      => $_POST['fund_id'] !== '' ? (int)$_POST['fund_id'] : null,
            'title'        => $title,
            'slug'         => $slug,
            'locale'       => in_array($_POST['locale'] ?? 'any', ['en','de','any'], true) ? $_POST['locale'] : 'any',
            'body'         => $_POST['body'] ?? '',
            'document_id'  => $_POST['document_id'] !== '' ? (int)$_POST['document_id'] : null,
            'publish_date' => $_POST['publish_date'] ?: date('Y-m-d'),
            'status'       => in_array($_POST['status'] ?? 'published', ['draft','published'], true) ? $_POST['status'] : 'published',
        ];

        if ($id) {
            $db->update('fund_announcements', $data, ['id' => $id]);
            AuditLog::log(Auth::userId(), 'announcement_updated', 'fund_announcements', $id, $title);
            flash('ok', 'Announcement updated.');
        } else {
            $data['created_by'] = Auth::userId();
            $newId = $db->insert('fund_announcements', $data);
            AuditLog::log(Auth::userId(), 'announcement_created', 'fund_announcements', $newId, $title);
            flash('ok', 'Announcement created.');
        }
        redirect(asset('admin/announcements.php'));
    }
}

$list  = $db->fetchAll('SELECT a.*, f.name_en AS fund_name FROM fund_announcements a LEFT JOIN funds f ON f.id = a.fund_id ORDER BY a.publish_date DESC, a.id DESC');
$funds = $db->fetchAll('SELECT id, name_en FROM funds WHERE status = "active" ORDER BY display_order');
$docs  = $db->fetchAll('SELECT id, title FROM documents ORDER BY created_at DESC LIMIT 200');

$editAnn = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($list as $a) if ($a['id'] == $editId) { $editAnn = $a; break; }
}

$adminPage = ['title' => 'Fund Announcements', 'crumb' => 'Brief narratives + linked documents — published to /announcements.php'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert err"><?= e($err) ?></div><?php endif; ?>

<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head">
        <h2><?= $editAnn ? 'Edit Announcement #' . $editAnn['id'] : 'New Announcement' ?></h2>
        <?php if ($editAnn): ?>
        <a class="a-btn ghost" href="<?= asset('admin/announcements.php') ?>"><i class="fa-solid fa-plus"></i> New</a>
        <?php endif; ?>
    </div>
    <div class="a-card__body">
        <form method="post" class="a-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="ann_id" value="<?= e($editAnn['id'] ?? '') ?>">

            <label>Title</label>
            <input type="text" name="title" value="<?= e($editAnn['title'] ?? '') ?>" required>

            <div class="row">
                <div>
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?= e($editAnn['slug'] ?? '') ?>" placeholder="auto-generated from title">
                </div>
                <div>
                    <label>Publish date</label>
                    <input type="date" name="publish_date" value="<?= e($editAnn['publish_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="published" <?= ($editAnn['status'] ?? 'published')==='published'?'selected':'' ?>>Published</option>
                        <option value="draft" <?= ($editAnn['status'] ?? '')==='draft'?'selected':'' ?>>Draft</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Language</label>
                    <select name="locale">
                        <option value="any" <?= ($editAnn['locale'] ?? 'any')==='any'?'selected':'' ?>>Both (EN + DE)</option>
                        <option value="en"  <?= ($editAnn['locale'] ?? '')==='en'?'selected':'' ?>>English only</option>
                        <option value="de"  <?= ($editAnn['locale'] ?? '')==='de'?'selected':'' ?>>German only</option>
                    </select>
                </div>
                <div>
                    <label>Related fund (optional)</label>
                    <select name="fund_id">
                        <option value="">— None / Umbrella —</option>
                        <?php foreach ($funds as $f): ?>
                        <option value="<?= e($f['id']) ?>" <?= (string)($editAnn['fund_id'] ?? '')===(string)$f['id']?'selected':'' ?>><?= e($f['name_en']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Attached document (optional)</label>
                    <select name="document_id">
                        <option value="">— None —</option>
                        <?php foreach ($docs as $d): ?>
                        <option value="<?= e($d['id']) ?>" <?= (string)($editAnn['document_id'] ?? '')===(string)$d['id']?'selected':'' ?>><?= e(mb_strimwidth($d['title'], 0, 80, '…')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label>Body (HTML allowed)</label>
            <textarea name="body" id="annBody" rows="8"><?= e($editAnn['body'] ?? '') ?></textarea>

            <button type="submit" class="a-btn"><i class="fa-solid fa-check"></i> <?= $editAnn ? 'Update Announcement' : 'Create Announcement' ?></button>
        </form>
    </div>
</div>

<div class="a-card">
    <div class="a-card__head"><h2><?= count($list) ?> announcement<?= count($list) !== 1 ? 's' : '' ?></h2></div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($list)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No announcements yet.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>Title</th><th>Fund</th><th>Date</th><th>Lang</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($list as $a): ?>
                <tr>
                    <td><strong><?= e($a['title']) ?></strong></td>
                    <td><small><?= e($a['fund_name'] ?? '—') ?></small></td>
                    <td><small><?= e(format_date($a['publish_date'])) ?></small></td>
                    <td><span class="a-badge muted"><?= e(strtoupper($a['locale'])) ?></span></td>
                    <td><span class="a-badge <?= $a['status']==='published'?'success':'warning' ?>"><?= e($a['status']) ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="a-btn ghost sm" href="<?= asset('admin/announcements.php?edit=' . $a['id']) ?>"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this announcement?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                            <button class="a-btn danger sm" type="submit"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- TinyMCE for body -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.0.0/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#annBody', height: 280, menubar: false, branding: false, license_key: 'gpl',
    plugins: 'lists link autolink',
    toolbar: 'undo redo | bold italic | bullist numlist | link | removeformat'
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
