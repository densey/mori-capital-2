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
$db = Database::instance();

// Add NAV entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_nav') {
    Csrf::requireValid();
    $scId = (int)($_POST['share_class_id'] ?? 0);
    try {
        $db->insert('nav_entries', [
            'share_class_id'  => $scId,
            'entry_date'      => $_POST['entry_date'],
            'nav'             => (float)$_POST['nav'],
            'benchmark_value' => $_POST['benchmark_value'] !== '' ? (float)$_POST['benchmark_value'] : null,
            'notes'           => trim($_POST['notes']) ?: null,
        ]);
        AuditLog::log(Auth::userId(), 'nav_added', 'nav_entries', null, "SC #{$scId} @ {$_POST['entry_date']}");
        flash('ok', 'NAV entry added.');
    } catch (\PDOException $e) {
        if (str_contains($e->getMessage(), 'uniq_class_date')) flash('error', 'A NAV for this share class and date already exists.');
        else flash('error', 'Could not save NAV: ' . $e->getMessage());
    }
    redirect(asset('admin/performance.php?class=' . $scId));
}

// CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    Csrf::requireValid();
    $scId = (int)($_POST['share_class_id'] ?? 0);
    try {
        if (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) throw new \Exception('Choose a CSV file.');
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) throw new \Exception('Cannot open CSV.');
        $imported = 0; $skipped = 0; $header = null;
        while (($row = fgetcsv($fh)) !== false) {
            if ($header === null) { $header = array_map('strtolower', array_map('trim', $row)); continue; }
            $r = array_combine($header, $row);
            if (empty($r['date']) || $r['nav'] === '') { $skipped++; continue; }
            try {
                $db->insert('nav_entries', [
                    'share_class_id'  => $scId,
                    'entry_date'      => date('Y-m-d', strtotime($r['date'])),
                    'nav'             => (float)$r['nav'],
                    'benchmark_value' => !empty($r['benchmark']) ? (float)$r['benchmark'] : null,
                ]);
                $imported++;
            } catch (\Throwable) { $skipped++; }
        }
        fclose($fh);
        AuditLog::log(Auth::userId(), 'nav_csv_imported', 'nav_entries', null, "SC #{$scId} - {$imported} imported / {$skipped} skipped");
        flash('ok', "Imported {$imported} entries, skipped {$skipped} (duplicates or invalid).");
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect(asset('admin/performance.php?class=' . $scId));
}

// Delete entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_nav') {
    Csrf::requireValid();
    $id = (int)$_POST['id'];
    $row = $db->fetchOne('SELECT * FROM nav_entries WHERE id=:id', ['id'=>$id]);
    if ($row) {
        $db->delete('nav_entries', ['id' => $id]);
        AuditLog::log(Auth::userId(), 'nav_deleted', 'nav_entries', $id, "SC #{$row['share_class_id']} @ {$row['entry_date']}");
        flash('ok', 'NAV entry deleted.');
    }
    redirect(asset('admin/performance.php?class=' . ($row['share_class_id'] ?? '')));
}

$funds = $db->fetchAll('SELECT * FROM funds ORDER BY display_order');
$selectedFundId = (int)($_GET['fund'] ?? ($funds[0]['id'] ?? 0));
$shareClasses = $selectedFundId ? $db->fetchAll('SELECT * FROM share_classes WHERE fund_id=:f ORDER BY display_order', ['f'=>$selectedFundId]) : [];
$selectedScId = (int)($_GET['class'] ?? ($shareClasses[0]['id'] ?? 0));
$navEntries = $selectedScId ? $db->fetchAll('SELECT * FROM nav_entries WHERE share_class_id=:s ORDER BY entry_date DESC', ['s'=>$selectedScId]) : [];

$adminPage = ['title' => 'Performance (NAV)', 'crumb' => 'Monthly NAV entry, CSV import and historical view'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= e($err) ?></div><?php endif; ?>

<!-- Selector -->
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__body">
        <form method="get" class="a-form row" style="margin-bottom:0;">
            <div>
                <label>Fund</label>
                <select name="fund" onchange="this.form.submit()">
                    <?php foreach ($funds as $f): ?>
                    <option value="<?= e($f['id']) ?>" <?= $selectedFundId==$f['id']?'selected':'' ?>><?= e($f['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Share class</label>
                <select name="class" onchange="this.form.submit()">
                    <?php foreach ($shareClasses as $sc): ?>
                    <option value="<?= e($sc['id']) ?>" <?= $selectedScId==$sc['id']?'selected':'' ?>><?= e($sc['name']) ?> — <?= e($sc['currency']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedScId): ?>
<div class="a-grid" style="grid-template-columns:1fr 1fr;gap:22px;">
    <!-- Add NAV -->
    <div class="a-card">
        <div class="a-card__head"><h2>Add monthly NAV</h2></div>
        <div class="a-card__body">
            <form method="post" class="a-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="add_nav">
                <input type="hidden" name="share_class_id" value="<?= e($selectedScId) ?>">
                <div class="row">
                    <div><label>Date *</label><input type="date" name="entry_date" required value="<?= e(date('Y-m-d')) ?>"></div>
                    <div><label>NAV *</label><input type="number" name="nav" step="0.0001" required></div>
                    <div><label>Benchmark</label><input type="number" name="benchmark_value" step="0.0001"></div>
                </div>
                <label>Notes</label>
                <input type="text" name="notes" placeholder="Optional">
                <button class="a-btn" type="submit" style="margin-top:14px;"><i class="fa-solid fa-plus"></i> Add entry</button>
            </form>
        </div>
    </div>

    <!-- CSV import -->
    <div class="a-card">
        <div class="a-card__head"><h2>Bulk import (CSV)</h2></div>
        <div class="a-card__body">
            <p style="font-size:13px;color:var(--a-muted);margin-bottom:14px;">CSV header: <code>date,nav,benchmark</code>. Date in YYYY-MM-DD or DD/MM/YYYY. Duplicates are skipped.</p>
            <form method="post" enctype="multipart/form-data" class="a-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="import_csv">
                <input type="hidden" name="share_class_id" value="<?= e($selectedScId) ?>">
                <input type="file" name="csv" accept=".csv" required>
                <button class="a-btn" type="submit" style="margin-top:14px;"><i class="fa-solid fa-file-csv"></i> Import CSV</button>
            </form>
        </div>
    </div>
</div>

<!-- Entries -->
<div class="a-card" style="margin-top:22px;">
    <div class="a-card__head">
        <h2>NAV history — <?= count($navEntries) ?> entries</h2>
    </div>
    <div class="a-card__body" style="padding:0;">
        <?php if (empty($navEntries)): ?>
        <div style="padding:30px;text-align:center;color:var(--a-muted);">No NAV entries yet for this share class.</div>
        <?php else: ?>
        <table class="a-table">
            <thead><tr><th>Date</th><th>NAV</th><th>Benchmark</th><th>Notes</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($navEntries as $n): ?>
                <tr>
                    <td><strong><?= e(format_date($n['entry_date'])) ?></strong></td>
                    <td style="font-family:monospace;"><?= e(number_format((float)$n['nav'], 4)) ?></td>
                    <td style="font-family:monospace;color:var(--a-muted);"><?= $n['benchmark_value'] !== null ? e(number_format((float)$n['benchmark_value'], 4)) : '—' ?></td>
                    <td><small><?= e($n['notes'] ?? '') ?></small></td>
                    <td style="text-align:right;">
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this NAV entry?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="del_nav">
                            <input type="hidden" name="id" value="<?= e($n['id']) ?>">
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
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
