<?php
require __DIR__ . '/../src/bootstrap.php';

use Mori\Auth;
use Mori\Csrf;
use Mori\Database;
use Mori\AuditLog;
use function Mori\e;
use function Mori\asset;
use function Mori\flash;
use function Mori\format_bytes;
use function Mori\format_date;
use function Mori\redirect;
use function Mori\setting;

Auth::requireLogin();
$db = Database::instance();

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    Csrf::requireValid();
    try {
        if (empty($_FILES['files'])) throw new \Exception('Choose files to upload.');
        $maxBytes = (int)(setting('upload_max_mb', '20')) * 1024 * 1024;
        // SVG intentionally excluded — they can carry inline <script> (stored XSS risk).
        // To allow SVG, sanitize server-side first (e.g. enshrined/svg-sanitizer).
        $allowedExt = ['jpg','jpeg','png','gif','webp'];
        $count = 0;
        foreach ($_FILES['files']['name'] as $i => $name) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) continue;
            if ($_FILES['files']['size'][$i] > $maxBytes) continue;
            $year = date('Y');
            $dir  = dirname(__DIR__) . '/uploads/media/' . $year;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $safe = bin2hex(random_bytes(6)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
            $target = $dir . '/' . $safe;
            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target)) {
                $info = @getimagesize($target);
                $db->insert('media', [
                    'file_path' => $year . '/' . $safe,
                    'file_name' => $name,
                    'file_size' => filesize($target),
                    'mime_type' => mime_content_type($target) ?: null,
                    'width'     => $info[0] ?? null,
                    'height'    => $info[1] ?? null,
                    'folder'    => trim($_POST['folder'] ?? '') ?: null,
                    'uploaded_by' => Auth::userId(),
                ]);
                $count++;
            }
        }
        AuditLog::log(Auth::userId(), 'media_uploaded', 'media', null, "$count files");
        flash('ok', "$count files uploaded.");
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect(asset('admin/media.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::requireValid();
    $id = (int)$_POST['id'];
    $row = $db->fetchOne('SELECT * FROM media WHERE id=:id', ['id'=>$id]);
    if ($row) {
        $path = dirname(__DIR__) . '/uploads/media/' . $row['file_path'];
        if (is_file($path)) @unlink($path);
        $db->delete('media', ['id' => $id]);
        AuditLog::log(Auth::userId(), 'media_deleted', 'media', $id, $row['file_name']);
        flash('ok', 'Media deleted.');
    }
    redirect(asset('admin/media.php'));
}

$media = $db->fetchAll('SELECT * FROM media ORDER BY created_at DESC LIMIT 200');

$adminPage = ['title' => 'Media Library', 'crumb' => 'Reusable images and assets'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= e($err) ?></div><?php endif; ?>

<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head"><h2>Upload images</h2></div>
    <div class="a-card__body">
        <form method="post" enctype="multipart/form-data" class="a-form row" style="margin:0;align-items:end;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload">
            <div><label>Folder (optional)</label><input type="text" name="folder" placeholder="team / hero / insights …"></div>
            <div style="flex:2;"><label>Files (JPG / PNG / GIF / WEBP)</label><input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" required></div>
            <button class="a-btn"><i class="fa-solid fa-upload"></i> Upload</button>
        </form>
    </div>
</div>

<div class="a-card">
    <div class="a-card__head"><h2><?= count($media) ?> items</h2></div>
    <div class="a-card__body">
        <?php if (empty($media)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No media yet.</div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
            <?php foreach ($media as $m): ?>
            <div style="border:1px solid var(--a-border);border-radius:8px;overflow:hidden;background:#fff;">
                <div style="aspect-ratio:1;background:var(--a-border-soft);">
                    <img src="<?= asset('uploads/media/' . e($m['file_path'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
                </div>
                <div style="padding:10px 12px;">
                    <div style="font-size:12px;font-weight:600;color:var(--a-navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($m['file_name']) ?></div>
                    <div style="font-size:11px;color:var(--a-muted);margin-top:2px;"><?= e($m['width']??'?') ?>×<?= e($m['height']??'?') ?> · <?= e(format_bytes((int)$m['file_size'])) ?></div>
                    <div style="display:flex;gap:4px;margin-top:8px;">
                        <input type="text" readonly value="<?= asset('uploads/media/' . e($m['file_path'])) ?>" onclick="this.select()" style="flex:1;padding:4px 6px;font-size:10px;border:1px solid var(--a-border);border-radius:3px;font-family:monospace;">
                        <form method="post" onsubmit="return confirm('Delete?');" style="margin:0;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($m['id']) ?>">
                            <button class="a-btn danger sm" style="padding:4px 8px;"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
