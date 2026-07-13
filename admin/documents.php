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

// Is the show_on_fund_page migration applied? Used to gate the flag column in
// inserts/updates and to show the toggle in the list.
$hasFundPageCol = false;
try {
    $hasFundPageCol = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'documents'
            AND COLUMN_NAME = 'show_on_fund_page'"
    ) > 0;
} catch (\Throwable) {}
$hasTitleDeCol = false;
try {
    $hasTitleDeCol = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'documents'
            AND COLUMN_NAME = 'title_de'"
    ) > 0;
} catch (\Throwable) {}

// AJAX: toggle "show on fund detail page" flag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_fund_page') {
    header('Content-Type: application/json');
    try {
        Csrf::requireValid();
        $id  = (int) ($_POST['id'] ?? 0);
        $val = !empty($_POST['val']) ? 1 : 0;
        if ($id <= 0) throw new \Exception('Missing document id.');
        if (!$hasFundPageCol) throw new \Exception('Run install.php to apply the pending migration first.');
        $db->update('documents', ['show_on_fund_page' => $val], ['id' => $id]);
        AuditLog::log(Auth::userId(), 'document_fund_page_toggled', 'documents', $id, $val ? 'shown' : 'hidden');
        echo json_encode(['ok' => true, 'val' => $val]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// AJAX: reorder documents (drag-and-drop)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reorder') {
    header('Content-Type: application/json');
    try {
        Csrf::requireValid();
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) throw new \Exception('No IDs provided.');
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE documents SET display_order = :ord WHERE id = :id');
        foreach ($ids as $pos => $id) {
            $stmt->execute(['ord' => (int)$pos + 1, 'id' => (int)$id]);
        }
        $pdo->commit();
        AuditLog::log(Auth::userId(), 'documents_reordered', 'documents', null, count($ids) . ' rows');
        echo json_encode(['ok' => true, 'count' => count($ids)]);
    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

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

        $docData = [
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
        ];
        if ($hasFundPageCol) {
            $docData['show_on_fund_page'] = isset($_POST['show_on_fund_page']) ? 1 : 0;
        }
        if ($hasTitleDeCol) {
            $docData['title_de'] = trim($_POST['title_de'] ?? '') ?: null;
        }
        $id = $db->insert('documents', $docData);

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
// Detect whether the display_order migration has been applied yet — without it
// the SELECT below would 500 on staging. If the column is missing the admin
// list still works, just without drag-reorder.
$hasOrderCol = false;
try {
    $hasOrderCol = (int)$db->fetchColumn(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'documents'
            AND COLUMN_NAME = 'display_order'"
    ) > 0;
} catch (\Throwable) {}

$where = []; $params = [];
if (!empty($_GET['fund']))     { $where[] = 'd.fund_id = :f';     $params['f'] = (int)$_GET['fund']; }
if (!empty($_GET['type']))     { $where[] = 'd.document_type = :t'; $params['t'] = (string)$_GET['type']; }
if (!empty($_GET['category']) && $hasOrderCol) { $where[] = 'd.category = :c'; $params['c'] = (string)$_GET['category']; }
elseif (!empty($_GET['category'])) { $where[] = 'd.category = :c'; $params['c'] = (string)$_GET['category']; }

// When a single category is being filtered AND the migration has been applied,
// order by display_order so the admin view matches what visitors see and
// drag-reorder takes effect immediately.
$orderBy = (!empty($_GET['category']) && $hasOrderCol)
    ? ' ORDER BY d.display_order ASC, d.created_at DESC'
    : ' ORDER BY d.created_at DESC';
$sql = 'SELECT d.*, f.name_en AS fund_name FROM documents d LEFT JOIN funds f ON f.id = d.fund_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . $orderBy;
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
                    <label style="margin-top:8px;">Title — DE <span style="font-weight:400;color:var(--a-muted);">(shown on the German site; leave blank to reuse EN)</span></label>
                    <input type="text" name="title_de" placeholder="z. B. Mori Ottoman Fund — Factsheet Q4 2025">
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
                    <label>Language *  <span style="font-size:11px;color:var(--a-muted);font-weight:400;">— who sees this file?</span></label>
                    <select name="locale" required>
                        <option value="any" selected>🌐 Both — shown on EN and DE sites (default)</option>
                        <option value="en">🇬🇧 English only — hidden on DE site</option>
                        <option value="de">🇩🇪 Deutsch only — hidden on EN site</option>
                    </select>
                    <div class="hint">Pick "Both" for documents like policies that are language-neutral. Pick a specific language when you have separate EN and DE files of the same document.</div>
                </div>
            </div>

            <!-- Scope selector with 3 explicit modes -->
            <div style="background:var(--a-border-soft);padding:14px 18px;border-radius:8px;margin:14px 0;">
                <label style="font-weight:700;">Scope *  <span style="color:var(--a-muted);font-weight:400;font-size:11px;">— who sees this document in the matrix?</span></label>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-weight:400;font-size:13px;">
                        <input type="radio" name="scope_mode" value="share_class" checked onchange="updScope()">
                        <span><strong>Per share class</strong> — for KIID, PRIIPs KID. Choose specific share class(es) below. Each share class sees its own file in the matrix.</span>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-weight:400;font-size:13px;">
                        <input type="radio" name="scope_mode" value="fund" onchange="updScope()">
                        <span><strong>Per fund</strong> — for Factsheet (one per fund). Choose the fund below. All share classes of that fund see the same file.</span>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-weight:400;font-size:13px;">
                        <input type="radio" name="scope_mode" value="umbrella" onchange="updScope()">
                        <span><strong>Umbrella (all share classes)</strong> — for Prospectus, Audited Accounts, Semi-Annual Accounts. Applies to every share class.</span>
                    </label>
                </div>
            </div>

            <!-- Per-fund picker (visible when scope=fund) -->
            <div id="scope-fund" style="display:none;">
                <label>Fund</label>
                <select name="fund_id_select">
                    <option value="">— select a fund —</option>
                    <?php foreach ($funds as $f): ?>
                    <option value="<?= e($f['id']) ?>"><?= e($f['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Per-share-class picker (visible when scope=share_class) -->
            <?php if (!empty($shareClasses)): ?>
            <div id="scope-sc">
                <label>Share class(es) — tick one or more</label>
                <div style="background:#fff;border:1px solid var(--a-border);border-radius:6px;padding:12px 14px;max-height:220px;overflow-y:auto;">
                    <?php $lastFundId = null; foreach ($shareClasses as $sc):
                        if ($lastFundId !== $sc['fund_id']):
                            if ($lastFundId !== null) echo '</div>';
                            $lastFundId = $sc['fund_id']; ?>
                        <div style="margin-top:8px;font-size:11px;font-weight:700;color:var(--a-navy);text-transform:uppercase;letter-spacing:0.08em;padding-top:6px;border-top:1px solid var(--a-border-soft);"><?= e($sc['fund_name']) ?></div>
                        <div style="padding:6px 0;">
                    <?php endif; ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;margin:2px 4px 2px 0;background:var(--a-border-soft);border-radius:4px;font-size:12.5px;cursor:pointer;font-weight:400;">
                        <input type="checkbox" name="share_classes[]" value="<?= e($sc['id']) ?>">
                        <?= e($sc['name']) ?> (<?= e($sc['currency']) ?>)
                    </label>
                    <?php endforeach; if ($lastFundId !== null) echo '</div>'; ?>
                </div>
            </div>
            <?php endif; ?>
            <input type="hidden" name="fund_id" id="fund_id_hidden" value="">

            <label>Description (shown to users on Company Policies / Other Documents listing)</label>
            <textarea name="description" rows="2" placeholder="Optional — shown beneath the title on policy/other-documents pages"></textarea>

            <label>File * (PDF / DOC / XLS / CSV / TXT, max <?= e(setting('upload_max_mb','20')) ?>MB)</label>
            <input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">

            <label>Version notes (optional, internal)</label>
            <textarea name="version_notes" rows="2" placeholder="e.g. v2 — corrects holdings table on p.3"></textarea>

            <?php if ($hasFundPageCol): ?>
            <label style="display:flex;align-items:center;gap:9px;cursor:pointer;margin-top:14px;font-weight:600;">
                <input type="checkbox" name="show_on_fund_page" value="1">
                <span>Show this document in the &ldquo;Fund documentation&rdquo; box on the fund detail page</span>
            </label>
            <p style="font-size:12px;color:var(--a-muted);margin:4px 0 0 26px;">Only documents linked to a fund appear there, and only on the matching language site. Leave unticked to keep it in the Document Hub / listings only.</p>
            <?php endif; ?>

            <script>
            function updScope() {
                var mode = document.querySelector('input[name="scope_mode"]:checked').value;
                document.getElementById('scope-fund').style.display = mode === 'fund' ? 'block' : 'none';
                var sc = document.getElementById('scope-sc');
                if (sc) sc.style.display = mode === 'share_class' ? 'block' : 'none';
                // Sync fund_id hidden field
                var hidden = document.getElementById('fund_id_hidden');
                if (mode === 'fund') {
                    var sel = document.querySelector('select[name="fund_id_select"]');
                    hidden.value = sel ? sel.value : '';
                    if (sel) sel.onchange = function() { hidden.value = this.value; };
                } else {
                    hidden.value = '';  // share_class + umbrella → no fund_id (umbrella) or fund inferred from share class
                }
                // Clear share class checkboxes when not in share_class mode
                if (mode !== 'share_class') {
                    document.querySelectorAll('input[name="share_classes[]"]').forEach(function(c){ c.checked = false; });
                }
            }
            window.addEventListener('DOMContentLoaded', updScope);
            </script>

            <button class="a-btn lg" type="submit" style="margin-top:20px;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload document</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="a-card">
    <div class="a-card__head">
        <form method="get" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
            <div>
                <label style="display:block;font-size:11px;color:var(--a-muted);font-weight:600;margin-bottom:4px;">Section</label>
                <select name="category" onchange="this.form.submit()" class="input" style="padding:6px 10px;font-size:13px;">
                    <option value="">All sections</option>
                    <?php foreach (['share_class'=>'Share Class (FundHub)','company_policy'=>'Company Policies','other'=>'Other Documents','suspension_update'=>'Updates During Suspension'] as $k => $lbl): ?>
                    <option value="<?= e($k) ?>" <?= ($_GET['category'] ?? '') === $k?'selected':'' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
    <?php $canReorder = !empty($_GET['category']) && $hasOrderCol; ?>
    <?php if ($canReorder): ?>
    <div style="padding:10px 18px;background:#E8F8F4;border-bottom:1px solid var(--a-border);color:#0F6B5C;font-size:12.5px;">
        <i class="fa-solid fa-grip-vertical"></i> Drag any row by its handle to reorder. Changes save automatically.
        <span id="reorderStatus" style="margin-left:10px;font-weight:600;"></span>
    </div>
    <?php elseif (!empty($_GET['category']) && !$hasOrderCol): ?>
    <div style="padding:10px 18px;background:#FFF3CD;border-bottom:1px solid var(--a-border);color:#8B5A00;font-size:12.5px;">
        <i class="fa-solid fa-triangle-exclamation"></i> Drag-and-drop reordering needs a one-time database update. Visit <a href="<?= asset('install.php') ?>" style="color:#8B5A00;text-decoration:underline;font-weight:600;">install.php</a> to apply pending migrations.
    </div>
    <?php else: ?>
    <div style="padding:10px 18px;background:var(--a-border-soft);border-bottom:1px solid var(--a-border);color:var(--a-muted);font-size:12px;">
        <i class="fa-solid fa-circle-info"></i> Choose a <strong>Section</strong> above to enable drag-and-drop reordering.
    </div>
    <?php endif; ?>
    <div class="a-card__body" style="padding:0;">
        <table class="a-table" id="docTable">
            <thead>
                <tr>
                    <?php if ($canReorder): ?><th style="width:30px;"></th><?php endif; ?>
                    <th>Title</th><th>Fund</th><th>Type</th><th>Date</th><th>Size</th><th>Downloads</th><th></th>
                </tr>
            </thead>
            <tbody id="docRows">
                <?php if (empty($documents)): ?>
                <tr><td colspan="<?= $canReorder ? 8 : 7 ?>" style="padding:30px;text-align:center;color:var(--a-muted);">No documents yet.</td></tr>
                <?php else: foreach ($documents as $d): ?>
                <tr data-id="<?= e($d['id']) ?>">
                    <?php if ($canReorder): ?>
                    <td style="cursor:grab;color:var(--a-muted);text-align:center;" class="drag-handle" title="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></td>
                    <?php endif; ?>
                    <td><strong><?= e($d['title']) ?></strong><br><small><?= e($d['file_name']) ?></small></td>
                    <td><?= e($d['fund_name'] ?? '—') ?></td>
                    <td>
                        <span class="a-badge teal"><?= e(strtoupper(str_replace('_',' ',$d['document_type']))) ?></span>
                        <span class="a-badge muted" style="font-size:10px;" title="Language"><?= e(strtoupper((string)($d['locale'] ?? 'any'))) ?></span>
                    </td>
                    <td><small><?= e(format_date($d['document_date'])) ?></small></td>
                    <td><small><?= e(format_bytes((int)$d['file_size'])) ?></small></td>
                    <td><?= e($d['download_count']) ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <?php if ($hasFundPageCol && !empty($d['fund_id'])): $on = (int)($d['show_on_fund_page'] ?? 0); ?>
                        <button type="button" class="a-btn ghost sm fund-page-toggle" data-id="<?= e($d['id']) ?>" data-on="<?= $on ?>"
                                title="<?= $on ? 'Shown on the fund detail page — click to hide' : 'Hidden from the fund detail page — click to show' ?>">
                            <i class="fa-<?= $on ? 'solid' : 'regular' ?> fa-star" style="color:<?= $on ? '#1ABC9C' : 'var(--a-muted)' ?>;"></i>
                        </button>
                        <?php endif; ?>
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

<?php if ($canReorder): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    var tbody = document.getElementById('docRows');
    var status = document.getElementById('reorderStatus');
    var csrf = <?= json_encode(Csrf::token()) ?>;
    if (!tbody) return;

    var sortable = Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'reorder-ghost',
        onEnd: function () {
            var rows = tbody.querySelectorAll('tr[data-id]');
            var ids = Array.from(rows).map(function (r) { return r.getAttribute('data-id'); });
            status.textContent = 'Saving…';
            status.style.color = '#0F6B5C';
            var fd = new FormData();
            fd.append('action', 'reorder');
            fd.append('_csrf', csrf);
            ids.forEach(function (id) { fd.append('ids[]', id); });
            fetch(window.location.pathname, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.ok) {
                        status.textContent = '✓ Saved (' + j.count + ' rows)';
                        setTimeout(function () { status.textContent = ''; }, 2500);
                    } else {
                        status.textContent = 'Save failed: ' + (j && j.error || 'unknown');
                        status.style.color = '#C0392B';
                    }
                })
                .catch(function () {
                    status.textContent = 'Network error — try again.';
                    status.style.color = '#C0392B';
                });
        }
    });
})();
</script>
<style>
.reorder-ghost { opacity: .4; background: #E8F8F4 !important; }
#docTable tr[data-id]:hover .drag-handle { color: var(--a-teal) !important; }
</style>
<?php endif; ?>

<?php if ($hasFundPageCol): ?>
<!-- Toggle "show on fund detail page" (★) -->
<script>
(function () {
    var csrf = <?= json_encode(Csrf::token()) ?>;
    document.querySelectorAll('.fund-page-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = btn.getAttribute('data-on') === '1' ? 0 : 1;
            var fd = new FormData();
            fd.append('action', 'toggle_fund_page');
            fd.append('_csrf', csrf);
            fd.append('id', btn.getAttribute('data-id'));
            fd.append('val', next);
            btn.disabled = true;
            fetch(window.location.pathname, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    btn.disabled = false;
                    if (!j || !j.ok) { alert('Could not update: ' + (j && j.error || 'unknown error')); return; }
                    btn.setAttribute('data-on', String(j.val));
                    var icon = btn.querySelector('i');
                    icon.className = 'fa-' + (j.val ? 'solid' : 'regular') + ' fa-star';
                    icon.style.color = j.val ? '#1ABC9C' : 'var(--a-muted)';
                    btn.title = j.val ? 'Shown on the fund detail page — click to hide' : 'Hidden from the fund detail page — click to show';
                })
                .catch(function () { btn.disabled = false; alert('Network error — try again.'); });
        });
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
