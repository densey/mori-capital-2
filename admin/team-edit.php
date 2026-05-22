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
$m = $id ? $db->fetchOne('SELECT * FROM team_members WHERE id=:id', ['id'=>$id]) : null;
$isNew = !$m;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $data = [
        'slug'         => slugify($_POST['slug'] ?? $_POST['name']),
        'name'         => trim($_POST['name']),
        'title_en'     => trim($_POST['title_en']),
        'title_de'     => trim($_POST['title_de']) ?: null,
        'bio_en'       => trim($_POST['bio_en']) ?: null,
        'bio_de'       => trim($_POST['bio_de']) ?: null,
        'photo_path'   => trim($_POST['photo_path']) ?: null,
        'linkedin_url' => trim($_POST['linkedin_url']) ?: null,
        'email'        => trim($_POST['email']) ?: null,
        'display_order'=> (int)$_POST['display_order'],
        'status'       => in_array($_POST['status'], ['active','inactive'], true) ? $_POST['status'] : 'active',
    ];
    if ($data['name'] === '') flash('error', 'Name is required.');
    else {
        try {
            if ($isNew) {
                $newId = $db->insert('team_members', $data);
                AuditLog::log(Auth::userId(), 'team_created', 'team_members', $newId, $data['name']);
                flash('ok', 'Team member added.');
                redirect(asset('admin/team-edit.php?id=' . $newId));
            } else {
                $db->update('team_members', $data, ['id' => $id]);
                AuditLog::log(Auth::userId(), 'team_updated', 'team_members', $id, $data['name']);
                flash('ok', 'Team member updated.');
                redirect(asset('admin/team-edit.php?id=' . $id));
            }
        } catch (\Throwable $ex) { flash('error', $ex->getMessage()); }
    }
}

$adminPage = ['title' => $isNew ? 'Add team member' : 'Edit team member', 'tinymce' => true];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="a-form">
    <?= Csrf::field() ?>
    <div class="a-grid" style="grid-template-columns:2fr 1fr;gap:22px;">
        <div>
            <div class="a-card"><div class="a-card__body">
                <div class="row">
                    <div><label>Name *</label><input type="text" name="name" required value="<?= e($m['name'] ?? '') ?>"></div>
                    <div><label>Slug</label><input type="text" name="slug" value="<?= e($m['slug'] ?? '') ?>" placeholder="auto"></div>
                </div>
                <div class="row">
                    <div><label>Title (EN) *</label><input type="text" name="title_en" required value="<?= e($m['title_en'] ?? '') ?>"></div>
                    <div><label>Title (DE)</label><input type="text" name="title_de" value="<?= e($m['title_de'] ?? '') ?>"></div>
                </div>
                <label>Photo path or URL</label>
                <input type="text" name="photo_path" value="<?= e($m['photo_path'] ?? '') ?>" placeholder="assets/images/team/name.jpg or https://...">
                <div class="row">
                    <div><label>LinkedIn URL</label><input type="url" name="linkedin_url" value="<?= e($m['linkedin_url'] ?? '') ?>"></div>
                    <div><label>Email</label><input type="email" name="email" value="<?= e($m['email'] ?? '') ?>"></div>
                </div>
                <label>Bio (English)</label>
                <textarea name="bio_en" class="wysiwyg"><?= e($m['bio_en'] ?? '') ?></textarea>
                <label>Bio (Deutsch)</label>
                <textarea name="bio_de" class="wysiwyg"><?= e($m['bio_de'] ?? '') ?></textarea>
            </div></div>
        </div>
        <aside>
            <div class="a-card"><div class="a-card__head"><h2>Publish</h2></div><div class="a-card__body">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= ($m['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= ($m['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
                <label>Display order</label>
                <input type="number" name="display_order" value="<?= e($m['display_order'] ?? 0) ?>">
                <button class="a-btn" type="submit" style="width:100%;margin-top:18px;justify-content:center;"><i class="fa-solid fa-save"></i> Save</button>
                <a class="a-btn ghost" href="<?= asset('admin/team.php') ?>" style="width:100%;margin-top:8px;justify-content:center;">Cancel</a>
            </div></div>
        </aside>
    </div>
</form>

<?php include __DIR__ . '/partials/footer.php'; ?>
