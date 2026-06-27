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

// Create table if not exists (in case migration hasn't run)
$db->query("CREATE TABLE IF NOT EXISTS hero_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NULL,
    subtitle VARCHAR(500) NULL,
    title_de VARCHAR(255) NULL,
    subtitle_de VARCHAR(500) NULL,
    media_type ENUM('image','video') NOT NULL DEFAULT 'image',
    media_path VARCHAR(500) NOT NULL,
    overlay_opacity DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    cta_text VARCHAR(100) NULL,
    cta_url VARCHAR(500) NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Older installs created the table before the bilingual columns existed.
// Add them idempotently so this admin page works whether or not the
// 2026-06-german-full-content migration has been run.
$hasTitleDe = $db->fetchOne(
    "SELECT COUNT(*) AS c FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'hero_slides' AND column_name = 'title_de'"
);
if ((int)($hasTitleDe['c'] ?? 0) === 0) {
    try { $db->query("ALTER TABLE hero_slides ADD COLUMN title_de VARCHAR(255) NULL AFTER subtitle"); } catch (\Throwable $e) {}
    try { $db->query("ALTER TABLE hero_slides ADD COLUMN subtitle_de VARCHAR(500) NULL AFTER title_de"); } catch (\Throwable $e) {}
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->delete('hero_slides', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'hero_deleted', 'hero_slides', $id);
            flash('ok', 'Slide deleted.');
        }
    }

    if ($action === 'save') {
        $id = (int)($_POST['slide_id'] ?? 0);
        $data = [
            'title'           => trim($_POST['title'] ?? ''),
            'subtitle'        => trim($_POST['subtitle'] ?? ''),
            'title_de'        => trim($_POST['title_de'] ?? ''),
            'subtitle_de'     => trim($_POST['subtitle_de'] ?? ''),
            'media_type'      => in_array($_POST['media_type'] ?? '', ['image','video']) ? $_POST['media_type'] : 'image',
            'media_path'      => trim($_POST['media_path'] ?? ''),
            'overlay_opacity' => max(0, min(1, (float)($_POST['overlay_opacity'] ?? 0.7))),
            'cta_text'        => trim($_POST['cta_text'] ?? ''),
            'cta_url'         => trim($_POST['cta_url'] ?? ''),
            'display_order'   => (int)($_POST['display_order'] ?? 0),
            'is_active'       => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($data['media_path'] === '') {
            flash('error', 'Media path is required.');
            redirect(asset('admin/hero.php'));
        }

        if ($id) {
            $db->update('hero_slides', $data, ['id' => $id]);
            AuditLog::log(Auth::userId(), 'hero_updated', 'hero_slides', $id, $data['title']);
            flash('ok', 'Slide updated.');
        } else {
            $newId = $db->insert('hero_slides', $data);
            AuditLog::log(Auth::userId(), 'hero_created', 'hero_slides', $newId, $data['title']);
            flash('ok', 'Slide created.');
        }
    }

    if ($action === 'reorder' && !empty($_POST['order'])) {
        $order = json_decode($_POST['order'], true);
        if (is_array($order)) {
            foreach ($order as $pos => $slideId) {
                $db->update('hero_slides', ['display_order' => $pos + 1], ['id' => (int)$slideId]);
            }
            flash('ok', 'Order updated.');
        }
    }

    redirect(asset('admin/hero.php'));
}

// Handle AJAX upload
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'upload') {
    // Handled by existing upload-file.php
}

$slides = $db->fetchAll('SELECT * FROM hero_slides ORDER BY display_order, id');
$editSlide = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($slides as $s) { if ($s['id'] == $editId) { $editSlide = $s; break; } }
}

$adminPage = ['title' => 'Hero Slider', 'crumb' => 'Manage homepage hero images, videos and text'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert err"><?= e($err) ?></div><?php endif; ?>

<!-- Edit / Add form -->
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head">
        <h2><?= $editSlide ? 'Edit Slide #' . $editSlide['id'] : 'Add New Slide' ?></h2>
        <?php if ($editSlide): ?>
        <a class="a-btn ghost" href="<?= asset('admin/hero.php') ?>"><i class="fa-solid fa-plus"></i> New slide</a>
        <?php endif; ?>
    </div>
    <div class="a-card__body">
        <form method="post" class="a-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="slide_id" value="<?= e($editSlide['id'] ?? '') ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label>Title — EN (overlay text)</label>
                    <input type="text" name="title" value="<?= e($editSlide['title'] ?? '') ?>" placeholder="Navigating EEMEA markets...">
                </div>
                <div>
                    <label>Title — DE <span style="font-weight:400;color:var(--a-muted);">(leave blank to reuse EN)</span></label>
                    <input type="text" name="title_de" value="<?= e($editSlide['title_de'] ?? '') ?>" placeholder="EEMEA-Märkte navigieren...">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px;">
                <div>
                    <label>Subtitle — EN</label>
                    <input type="text" name="subtitle" value="<?= e($editSlide['subtitle'] ?? '') ?>" placeholder="Independent and unconstrained...">
                </div>
                <div>
                    <label>Subtitle — DE <span style="font-weight:400;color:var(--a-muted);">(leave blank to reuse EN)</span></label>
                    <input type="text" name="subtitle_de" value="<?= e($editSlide['subtitle_de'] ?? '') ?>" placeholder="Unabhängig und unbeschränkt...">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:auto 1fr;gap:16px;margin-top:14px;">
                <div>
                    <label>Media type</label>
                    <select name="media_type" id="heroMediaType" onchange="toggleMediaPreview()">
                        <option value="image" <?= ($editSlide['media_type'] ?? 'image')==='image'?'selected':'' ?>>Image</option>
                        <option value="video" <?= ($editSlide['media_type'] ?? '')==='video'?'selected':'' ?>>Video (MP4)</option>
                    </select>
                </div>
                <div>
                    <label>Media path</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="media_path" id="heroMediaPath" value="<?= e($editSlide['media_path'] ?? '') ?>" placeholder="assets/images/hero/hero-istanbul.jpg" style="flex:1;" required>
                        <button type="button" class="a-btn ghost" onclick="document.getElementById('heroFileInput').click()"><i class="fa-solid fa-upload"></i> Upload</button>
                    </div>
                </div>
            </div>

            <input type="file" id="heroFileInput" accept="image/*,video/mp4" style="display:none;">

            <!-- Preview -->
            <div id="heroPreview" style="margin-top:14px;border-radius:8px;overflow:hidden;background:var(--a-border-soft);max-height:200px;">
                <?php if ($editSlide && $editSlide['media_path']): ?>
                    <?php if ($editSlide['media_type'] === 'video'): ?>
                        <video src="/<?= e(ltrim($editSlide['media_path'], '/')) ?>" style="width:100%;max-height:200px;object-fit:cover;" muted autoplay loop></video>
                    <?php else: ?>
                        <img src="/<?= e(ltrim($editSlide['media_path'], '/')) ?>" style="width:100%;max-height:200px;object-fit:cover;">
                    <?php endif; ?>
                <?php else: ?>
                    <div style="padding:40px;text-align:center;color:var(--a-muted);font-size:13px;">Upload or enter a media path to see preview</div>
                <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:16px;margin-top:14px;">
                <div>
                    <label>CTA button text</label>
                    <input type="text" name="cta_text" value="<?= e($editSlide['cta_text'] ?? '') ?>" placeholder="Explore Our Funds">
                </div>
                <div>
                    <label>CTA button URL</label>
                    <input type="text" name="cta_url" value="<?= e($editSlide['cta_url'] ?? '') ?>" placeholder="#funds">
                </div>
                <div>
                    <label>Overlay opacity (0–1)</label>
                    <input type="number" name="overlay_opacity" value="<?= e($editSlide['overlay_opacity'] ?? '0.70') ?>" min="0" max="1" step="0.05">
                </div>
                <div>
                    <label>Order</label>
                    <input type="number" name="display_order" value="<?= e($editSlide['display_order'] ?? count($slides) + 1) ?>" min="0" style="width:70px;">
                </div>
            </div>

            <div style="margin-top:14px;display:flex;align-items:center;gap:16px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                    <input type="checkbox" name="is_active" <?= ($editSlide['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <span>Active</span>
                </label>
                <button type="submit" class="a-btn"><i class="fa-solid fa-check"></i> <?= $editSlide ? 'Update Slide' : 'Add Slide' ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Existing slides -->
<div class="a-card">
    <div class="a-card__head">
        <h2><?= count($slides) ?> slide<?= count($slides) !== 1 ? 's' : '' ?></h2>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($slides)): ?>
        <div style="padding:40px;text-align:center;color:var(--a-muted);">No hero slides yet. Add your first one above.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th style="width:80px">Preview</th><th>Title</th><th>Type</th><th>Order</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($slides as $s): ?>
            <tr>
                <td>
                    <?php if ($s['media_type'] === 'video'): ?>
                    <div style="width:80px;height:45px;background:#0E1F36;border-radius:4px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-play" style="color:#1ABC9C;font-size:16px;"></i>
                    </div>
                    <?php else: ?>
                    <img src="/<?= e(ltrim($s['media_path'], '/')) ?>" style="width:80px;height:45px;object-fit:cover;border-radius:4px;" onerror="this.style.background='#E1E7EE'">
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= e($s['title'] ?: '(no title)') ?></strong>
                    <?php $hasDe = !empty($s['title_de']) || !empty($s['subtitle_de']); ?>
                    <span class="a-badge <?= $hasDe ? 'success' : 'muted' ?>" style="font-size:10px;margin-left:6px;vertical-align:middle;" title="<?= $hasDe ? 'German translation set' : 'No German translation — DE site reuses the English text' ?>"><?= $hasDe ? 'EN · DE' : 'EN only' ?></span>
                    <?php if ($s['subtitle']): ?><br><small style="color:var(--a-muted);"><?= e(mb_strimwidth($s['subtitle'], 0, 60, '…')) ?></small><?php endif; ?>
                </td>
                <td><span class="a-badge muted"><?= e($s['media_type']) ?></span></td>
                <td><?= e($s['display_order']) ?></td>
                <td><span class="a-badge <?= $s['is_active'] ? 'success' : 'muted' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td style="text-align:right;white-space:nowrap;">
                    <a class="a-btn ghost sm" href="<?= asset('admin/hero.php?edit=' . $s['id']) ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this slide?');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e($s['id']) ?>">
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

<script>
var csrfToken = <?= json_encode(Csrf::token()) ?>;

// File upload
document.getElementById('heroFileInput').addEventListener('change', function() {
    if (!this.files[0]) return;
    var file = this.files[0];
    var fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', csrfToken);
    fd.append('folder', 'hero');

    var isVideo = file.type.startsWith('video/');
    var preview = document.getElementById('heroPreview');
    preview.innerHTML = '<div style="padding:20px;text-align:center;color:var(--a-muted);">Uploading...</div>';

    fetch('/admin/api/upload-file.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(j) {
        if (j.ok) {
            var path = j.url || ('/' + j.path);
            document.getElementById('heroMediaPath').value = j.path;
            if (isVideo) {
                document.getElementById('heroMediaType').value = 'video';
                preview.innerHTML = '<video src="' + path + '" style="width:100%;max-height:200px;object-fit:cover;" muted autoplay loop></video>';
            } else {
                document.getElementById('heroMediaType').value = 'image';
                preview.innerHTML = '<img src="' + path + '" style="width:100%;max-height:200px;object-fit:cover;">';
            }
        } else {
            preview.innerHTML = '<div style="padding:20px;text-align:center;color:#E74C3C;">' + (j.error || 'Upload failed') + '</div>';
        }
    })
    .catch(function() {
        preview.innerHTML = '<div style="padding:20px;text-align:center;color:#E74C3C;">Upload error</div>';
    });
    this.value = '';
});

function toggleMediaPreview() {
    var path = document.getElementById('heroMediaPath').value;
    var type = document.getElementById('heroMediaType').value;
    var preview = document.getElementById('heroPreview');
    if (!path) return;
    if (type === 'video') {
        preview.innerHTML = '<video src="/' + path.replace(/^\//, '') + '" style="width:100%;max-height:200px;object-fit:cover;" muted autoplay loop></video>';
    } else {
        preview.innerHTML = '<img src="/' + path.replace(/^\//, '') + '" style="width:100%;max-height:200px;object-fit:cover;">';
    }
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
