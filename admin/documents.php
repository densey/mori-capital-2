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
use function Mori\format_bytes;
use function Mori\redirect;
use function Mori\setting;

Auth::requireLogin();
$db = Database::instance();

// Delete document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Csrf::requireValid();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $doc = $db->fetchOne('SELECT * FROM documents WHERE id=:id', ['id' => $id]);
        if ($doc) {
            $path = dirname(__DIR__) . '/uploads/documents/' . $doc['file_path'];
            if (is_file($path)) @unlink($path);
            $db->delete('documents', ['id' => $id]);
            AuditLog::log(Auth::userId(), 'document_deleted', 'documents', $id, $doc['title']);
            flash('ok', 'Document deleted.');
        }
    }
    redirect(asset('admin/documents.php'));
}

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    Csrf::requireValid();
    try {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Please choose a file.');
        }

        $maxBytes = (int) (setting('upload_max_mb', '20') ?? 20) * 1024 * 1024;
        if ($_FILES['file']['size'] > $maxBytes) {
            throw new \Exception('File exceeds the size limit.');
        }

        $allowedExt = ['pdf','doc','docx','xls','xlsx','csv','txt'];
        $orig = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new \Exception('File type not allowed. Permitted: ' . implode(', ', $allowedExt));
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') throw new \Exception('Document title is required.');

        $fundId = (int)($_POST['fund_id'] ?? 0) ?: null;
        $type   = $_POST['document_type'] ?? 'other';
        $allowedTypes = ['prospectus','kiid','priips','annual','semi_annual','factsheet','marketing','other'];
        if (!in_array($type, $allowedTypes, true)) $type = 'other';

        // Build safe storage path
        $year = date('Y');
        $dir = dirname(__DIR__) . '/uploads/documents/' . $year;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $safeName = bin2hex(random_bytes(6)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
        $stored   = $year . '/' . $safeName;
        $target   = $dir . '/' . $safeName;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            throw new \Exception('Could not store the uploaded file.');
        }

        $category = $_POST['category'] ?? 'share_class';
        $allowedCats = ['share_class','company_policy','other','suspension_update'];
        if (!in_array($category, $allowedCats, true)) $category = 'share_class';

        $id = $db->insert('documents', [
            'fund_id'       => $fundId,
            'document_type' => $type,
            'category'      => $category,
            'title'         => $title,
            'description'   => trim($_POST['description'] ?? '') ?: null,
            'file_path'     => $stored,
            'file_name'     => $orig,
            'file_size'     => filesize($target),
            'mime_type'     => mime_content_type($target) ?: null,
            'document_date' => $_POST['document_date'] ?: null,
            'display_year'  => !empty($_POST['display_year']) ? (int)$_POST['display_year'] : null,
            'locale'        => in_array($_POST['locale'] ?? 'any', ['en','de','any'], true) ? $_POST['locale'] : 'any',
            'version_notes' => trim($_POST['version_notes'] ?? '') ?: null,
            'uploaded_by'   => Auth::userId(),
        ]);

        // Many-to-many share classes
        if (!empty($_POST['share_classes']) && is_array($_POST['share_classes'])) {
            foreach ($_POST['share_classes'] as $scId) {
                $db->insert('document_share_classes', [
                    'document_id'    => $id,
                    'share_class_id' => (int)$scId,
                ]);
            }
        }

        AuditLog::log(Auth::userId(), 'document_uploaded', 'documents', $id, $title . ' (' . $type . ')');
        flash('ok', 'Document uploaded.');
        redirect(asset('admin/documents.php'));
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
        redirect(asset('admin/documents.php?action=upload'));
    }
}

// List
$where = []; $params = [];
if (!empty($_GET['fund'])) { $where[] = 'd.fund_id = :f'; $params['f'] = (int)$_GET['fund']; }
if (!empty($_GET['type'])) { $where[] = 'd.document_type = :t'; $params['t'] = (string)$_GET['type']; }
$sql = 'SELECT d.*, f.name_en AS fund_name FROM documents d LEFT JOIN funds f ON f.id = d.fund_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY d.created_at DESC';
$documents = $db->fetchAll($sql, $params);
$funds = $db->fetchAll('SELECT * FROM funds ORDER BY display_order');
$shareClasses = $db->fetchAll('SELECT sc.*, f.name_en AS fund_name FROM share_classes sc LEFT JOIN funds f ON f.id=sc.fund_id ORDER BY f.display_order, sc.display_order');

$showUpload = ($_GET['action'] ?? '') === 'upload';

$adminPage = ['title' => 'Documents', 'crumb' => 'Fund prospectus, KIIDs, factsheets and reports'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= e($err) ?></div><?php endif; ?>

<?php if ($showUpload): ?>
<!-- Upload form -->
<div class="a-card" style="margin-bottom:24px;">
    <div class="a-card__head">
        <h2><i class="fa-solid fa-upload"></i> Upload new document</h2>
        <a class="a-btn ghost sm" href="<?= asset('admin/documents.php') ?>">Cancel</a>
    </div>
    <div class="a-card__body">
        <form method="post" enctype="multipart/form-data" class="a-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload">

            <div class="row">
                <div>
                    <label>Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Mori Ottoman Fund — Q4 2025 Factsheet">
                </div>
                <div>
                    <label>Document date</label>
                    <input type="date" name="document_date" value="<?= e(date('Y-m-d')) ?>">
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="share_class">Share Class Document (FundHub matrix)</option>
                        <option value="company_policy">Company Policy</option>
                        <option value="other">Other Document (year-grouped)</option>
                        <option value="suspension_update">Update During Suspension</option>
                    </select>
                    <div class="hint">Determines where the document appears on the public site.</div>
                </div>
                <div>
                    <label>Display year (for "Other Documents")</label>
                    <input type="number" name="display_year" min="2020" max="2099" placeholder="e.g. 2026">
                    <div class="hint">Used to group documents in the "Other Documents" section. Defaults to document date's year.</div>
                </div>
            </div>

            <div class="row">
                <div>
                    <label>Fund / Scope</label>
                    <select name="fund_id">
                        <option value="">— Umbrella / global (applies to all share classes)</option>
                        <?php foreach ($funds as $f): ?>
                        <option value="<?= e($f['id']) ?>"><?= e($f['name_en']) ?> (per-fund)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Document type *</label>
                    <select name="document_type" required>
                        <option value="prospectus">Prospectus</option>
                        <option value="kiid">KIID</option>
                        <option value="priips">PRIIPs KID</option>
                        <option value="annual">Annual Report</option>
                        <option value="semi_annual">Semi-Annual Report</option>
                        <option value="factsheet" selected>Factsheet</option>
                        <option value="marketing">Marketing</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label>Language *</label>
                    <select name="locale" required>
                        <option value="en">English</option>
                        <option value="de">Deutsch</option>
                        <option value="any">Any (shown to both languages)</option>
                    </select>
                </div>
            </div>

            <label>Description (shown to users)</label>
            <textarea name="description" rows="2" placeholder="Brief description shown on Company Policies / Other Documents listing"></textarea>

            <label>File * (PDF / DOC / XLS / CSV / TXT, max <?= e(setting('upload_max_mb','20')) ?>MB)</label>
            <input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">

            <label>Version notes (optional)</label>
            <textarea name="version_notes" rows="2" placeholder="e.g. v2 — corrects holdings table on p.3"></textarea>

            <?php if (!empty($shareClasses)): ?>
            <label>Applies to share classes (optional — multi-select)</label>
            <select name="share_classes[]" multiple size="6" style="height:auto;">
                <?php foreach ($shareClasses as $sc): ?>
                <option value="<?= e($sc['id']) ?>"><?= e($sc['fund_name']) ?> — <?= e($sc['name']) ?> (<?= e($sc['currency']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Hold Ctrl/Cmd to select multiple.</div>
            <?php endif; ?>

            <button class="a-btn lg" type="submit" style="margin-top:20px;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload document</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="a-card">
    <div class="a-card__head">
        <form method="get" style="display:flex;gap:8px;align-items:end;">
            <div>
                <label style="display:block;font-size:11px;color:var(--a-muted);font-weight:600;margin-bottom:4px;">Fund</label>
                <select name="fund" onchange="this.form.submit()" class="input" style="padding:6px 10px;font-size:13px;">
                    <option value="">All funds</option>
                    <?php foreach ($funds as $f): ?>
                    <option value="<?= e($f['id']) ?>" <?= ($_GET['fund'] ?? '') == $f['id']?'selected':'' ?>><?= e($f['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;color:var(--a-muted);font-weight:600;margin-bottom:4px;">Type</label>
                <select name="type" onchange="this.form.submit()" class="input" style="padding:6px 10px;font-size:13px;">
                    <option value="">All types</option>
                    <?php foreach (['prospectus'=>'Prospectus','kiid'=>'KIID','priips'=>'PRIIPs','annual'=>'Annual','semi_annual'=>'Semi-Annual','factsheet'=>'Factsheet','marketing'=>'Marketing','other'=>'Other'] as $k=>$lbl): ?>
                    <option value="<?= e($k) ?>" <?= ($_GET['type'] ?? '') === $k?'selected':'' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <a class="a-btn" href="<?= asset('admin/documents.php?action=upload') ?>"><i class="fa-solid fa-upload"></i> Upload</a>
    </div>
    <div class="a-card__body" style="padding:0;">
        <table class="a-table">
            <thead>
                <tr><th>Title</th><th>Fund</th><th>Type</th><th>Date</th><th>Size</th><th>Downloads</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($documents)): ?>
                <tr><td colspan="7" style="padding:30px;text-align:center;color:var(--a-muted);">No documents yet.</td></tr>
                <?php else: foreach ($documents as $d): ?>
                <tr>
                    <td><strong><?= e($d['title']) ?></strong><br><small><?= e($d['file_name']) ?></small></td>
                    <td><?= e($d['fund_name'] ?? '—') ?></td>
                    <td><span class="a-badge teal"><?= e(strtoupper(str_replace('_',' ',$d['document_type']))) ?></span></td>
                    <td><small><?= e(format_date($d['document_date'])) ?></small></td>
                    <td><small><?= e(format_bytes((int)$d['file_size'])) ?></small></td>
                    <td><?= e($d['download_count']) ?></td>
                    <td style="text-align:right;">
                        <a class="a-btn ghost sm" href="<?= asset('api/download.php?id=' . (int)$d['id']) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-download"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this document?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($d['id']) ?>">
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
