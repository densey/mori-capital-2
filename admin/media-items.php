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

// Ensure table exists (in case the migration hasn't run yet)
$db->query("CREATE TABLE IF NOT EXISTS media_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_date DATE NULL,
    source VARCHAR(200) NULL,
    title_en VARCHAR(400) NOT NULL,
    title_de VARCHAR(400) NULL,
    url_en VARCHAR(700) NULL,
    url_de VARCHAR(700) NULL,
    item_type ENUM('press_release','article','video','podcast') NOT NULL DEFAULT 'article',
    is_external TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('published','draft') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_date (status, item_date),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$TYPES = ['press_release', 'article', 'video', 'podcast'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->delete('media_items', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'media_deleted', 'media_items', $id);
            flash('ok', 'Media item deleted.');
        }
        redirect(asset('admin/media-items.php'));
    }

    if ($action === 'reorder' && !empty($_POST['order'])) {
        $order = json_decode((string)$_POST['order'], true);
        if (is_array($order)) {
            foreach ($order as $pos => $itemId) {
                $db->update('media_items', ['display_order' => ($pos + 1) * 10], ['id' => (int)$itemId]);
            }
            flash('ok', 'Order updated.');
        }
        redirect(asset('admin/media-items.php'));
    }

    if ($action === 'save') {
        $id = (int)($_POST['item_id'] ?? 0);
        $titleEn = trim($_POST['title_en'] ?? '');
        if ($titleEn === '') {
            flash('error', 'English title is required.');
            redirect(asset('admin/media-items.php'));
        }
        $type = in_array($_POST['item_type'] ?? '', $TYPES, true) ? $_POST['item_type'] : 'article';

        $data = [
            'item_date'     => ($_POST['item_date'] ?? '') !== '' ? $_POST['item_date'] : null,
            'source'        => trim($_POST['source'] ?? ''),
            'title_en'      => $titleEn,
            'title_de'      => trim($_POST['title_de'] ?? ''),
            'url_en'        => trim($_POST['url_en'] ?? ''),
            'url_de'        => trim($_POST['url_de'] ?? ''),
            'item_type'     => $type,
            'is_external'   => isset($_POST['is_external']) ? 1 : 0,
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'status'        => in_array($_POST['status'] ?? 'published', ['published','draft'], true) ? $_POST['status'] : 'published',
        ];

        if ($id) {
            $db->update('media_items', $data, ['id' => $id]);
            AuditLog::log(Auth::userId(), 'media_updated', 'media_items', $id, $titleEn);
            flash('ok', 'Media item updated.');
        } else {
            $newId = $db->insert('media_items', $data);
            AuditLog::log(Auth::userId(), 'media_created', 'media_items', $newId, $titleEn);
            flash('ok', 'Media item created.');
        }
        redirect(asset('admin/media-items.php'));
    }
}

$list = $db->fetchAll('SELECT * FROM media_items ORDER BY display_order ASC, item_date DESC, id DESC');
$editItem = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($list as $m) if ($m['id'] == $editId) { $editItem = $m; break; }
}

$typeLabels = [
    'press_release' => 'Press Release (own PDF)',
    'article'       => 'Media Coverage (external article)',
    'video'         => 'Video',
    'podcast'       => 'Podcast',
];

$adminPage = ['title' => 'Media', 'crumb' => 'Press releases, media coverage & videos — published to /media.php'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert err"><?= e($err) ?></div><?php endif; ?>

<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head">
        <h2><?= $editItem ? 'Edit Media Item #' . $editItem['id'] : 'New Media Item' ?></h2>
        <?php if ($editItem): ?>
        <a class="a-btn ghost" href="<?= asset('admin/media-items.php') ?>"><i class="fa-solid fa-plus"></i> New</a>
        <?php endif; ?>
    </div>
    <div class="a-card__body">
        <form method="post" class="a-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="item_id" value="<?= e($editItem['id'] ?? '') ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label>Type</label>
                    <select name="item_type" id="mType">
                        <?php foreach ($typeLabels as $val => $lbl): ?>
                        <option value="<?= e($val) ?>" <?= ($editItem['item_type'] ?? 'article')===$val?'selected':'' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Source <span style="font-weight:400;color:var(--a-muted);">(publication / outlet)</span></label>
                    <input type="text" name="source" value="<?= e($editItem['source'] ?? '') ?>" placeholder="Citywire — Chris Soley  /  Mori Capital Management">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px;">
                <div>
                    <label>Title — EN</label>
                    <input type="text" name="title_en" value="<?= e($editItem['title_en'] ?? '') ?>" required placeholder="Mori Ottoman Fund Awarded by €uro / €uro am Sonntag">
                </div>
                <div>
                    <label>Title — DE <span style="font-weight:400;color:var(--a-muted);">(leave blank to reuse EN)</span></label>
                    <input type="text" name="title_de" value="<?= e($editItem['title_de'] ?? '') ?>" placeholder="Mori Ottoman Fund erhält Auszeichnung …">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr auto;gap:16px;margin-top:14px;align-items:end;">
                <div>
                    <label>URL — EN <span style="font-weight:400;color:var(--a-muted);">(external link, or a PDF path / upload)</span></label>
                    <input type="text" name="url_en" id="mUrlEn" value="<?= e($editItem['url_en'] ?? '') ?>" placeholder="https://…  or  uploads/media/press/file.pdf">
                </div>
                <div>
                    <button type="button" class="a-btn ghost" onclick="document.getElementById('mFileEn').click()"><i class="fa-solid fa-upload"></i> Upload PDF</button>
                    <input type="file" id="mFileEn" accept="application/pdf" style="display:none;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr auto;gap:16px;margin-top:14px;align-items:end;">
                <div>
                    <label>URL — DE <span style="font-weight:400;color:var(--a-muted);">(German PDF/link; leave blank to reuse EN)</span></label>
                    <input type="text" name="url_de" id="mUrlDe" value="<?= e($editItem['url_de'] ?? '') ?>" placeholder="https://…  or  uploads/media/press/file-de.pdf">
                </div>
                <div>
                    <button type="button" class="a-btn ghost" onclick="document.getElementById('mFileDe').click()"><i class="fa-solid fa-upload"></i> Upload PDF</button>
                    <input type="file" id="mFileDe" accept="application/pdf" style="display:none;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:auto 1fr auto auto;gap:16px;margin-top:14px;align-items:end;">
                <div>
                    <label>Date</label>
                    <input type="date" name="item_date" value="<?= e($editItem['item_date'] ?? '') ?>">
                </div>
                <div>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:24px;">
                        <input type="checkbox" name="is_external" <?= (int)($editItem['is_external'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <span>Opens in a new tab (external link)</span>
                    </label>
                </div>
                <div>
                    <label>Order</label>
                    <input type="number" name="display_order" value="<?= e($editItem['display_order'] ?? (count($list) + 1) * 10) ?>" style="width:80px;">
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="published" <?= ($editItem['status'] ?? 'published')==='published'?'selected':'' ?>>Published</option>
                        <option value="draft" <?= ($editItem['status'] ?? '')==='draft'?'selected':'' ?>>Draft</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="a-btn"><i class="fa-solid fa-check"></i> <?= $editItem ? 'Update Item' : 'Create Item' ?></button>
            </div>
        </form>
    </div>
</div>

<div class="a-card">
    <div class="a-card__head"><h2><?= count($list) ?> media item<?= count($list) !== 1 ? 's' : '' ?></h2>
        <span style="font-size:12px;color:var(--a-muted);">Drag the <i class="fa-solid fa-grip-vertical"></i> handle to reorder</span>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($list)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No media items yet. Add one above, or run <code>import-media.php</code> to seed from the old site.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th style="width:30px"></th><th>Title</th><th>Type</th><th>Date</th><th>Source</th><th>DE</th><th>Status</th><th></th></tr></thead>
            <tbody id="mediaSortable">
                <?php foreach ($list as $m): ?>
                <tr data-id="<?= e($m['id']) ?>">
                    <td style="cursor:grab;color:var(--a-muted);text-align:center;" class="m-grip"><i class="fa-solid fa-grip-vertical"></i></td>
                    <td><strong><?= e(mb_strimwidth($m['title_en'], 0, 70, '…')) ?></strong></td>
                    <td><span class="a-badge muted"><?= e(str_replace('_', ' ', $m['item_type'])) ?></span></td>
                    <td><small><?= e($m['item_date'] ? format_date($m['item_date']) : '—') ?></small></td>
                    <td><small><?= e(mb_strimwidth((string)$m['source'], 0, 28, '…')) ?></small></td>
                    <td><span class="a-badge <?= trim((string)$m['title_de']) !== '' ? 'success' : 'muted' ?>"><?= trim((string)$m['title_de']) !== '' ? '✓' : '—' ?></span></td>
                    <td><span class="a-badge <?= $m['status']==='published'?'success':'warning' ?>"><?= e($m['status']) ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="a-btn ghost sm" href="<?= asset('admin/media-items.php?edit=' . $m['id']) ?>"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this media item?');">
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
        <form method="post" id="reorderForm" style="display:none;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="order" id="reorderOrder">
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
var csrfToken = <?= json_encode(Csrf::token()) ?>;

// PDF upload helper — writes the stored path into the target URL field
function wireUpload(fileInputId, urlInputId) {
    var fi = document.getElementById(fileInputId);
    if (!fi) return;
    fi.addEventListener('change', function () {
        if (!this.files[0]) return;
        var fd = new FormData();
        fd.append('file', this.files[0]);
        fd.append('_csrf', csrfToken);
        fd.append('folder', 'media/press');
        var urlInput = document.getElementById(urlInputId);
        var prev = urlInput.value;
        urlInput.value = 'Uploading…';
        fetch('/admin/api/upload-file.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) { urlInput.value = j.ok ? j.path : prev; if (!j.ok) alert(j.error || 'Upload failed'); })
            .catch(function () { urlInput.value = prev; alert('Upload error'); });
        this.value = '';
    });
}
wireUpload('mFileEn', 'mUrlEn');
wireUpload('mFileDe', 'mUrlDe');

// Drag-to-reorder
(function () {
    var tbody = document.getElementById('mediaSortable');
    if (!tbody || !window.Sortable) return;
    Sortable.create(tbody, {
        handle: '.m-grip',
        animation: 150,
        onEnd: function () {
            var ids = Array.prototype.map.call(tbody.querySelectorAll('tr'), function (tr) { return tr.getAttribute('data-id'); });
            document.getElementById('reorderOrder').value = JSON.stringify(ids);
            document.getElementById('reorderForm').submit();
        }
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

<?php include __DIR__ . '/partials/footer.php'; ?>
