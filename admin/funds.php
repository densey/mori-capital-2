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
use function Mori\slugify;

Auth::requireLogin();
$db = Database::instance();

// Save share class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_sc') {
    Csrf::requireValid();
    $scId = (int)($_POST['sc_id'] ?? 0);

    $name     = trim($_POST['name'] ?? '');
    $currency = strtoupper(trim($_POST['currency'] ?? ''));
    $isin     = trim($_POST['isin'] ?? '');

    // Server-side validation
    if ($name === '') {
        flash('error', 'Share class name is required.');
        redirect(asset('admin/funds.php'));
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        flash('error', 'Currency must be a 3-letter ISO code (e.g. EUR, USD, GBP).');
        redirect(asset('admin/funds.php'));
    }
    if ($isin !== '' && !preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($isin))) {
        flash('error', 'ISIN must be 12 characters (2-letter country + 9 alnum + 1 check digit).');
        redirect(asset('admin/funds.php'));
    }

    $data = [
        'fund_id'        => (int)($_POST['fund_id'] ?? 0),
        'name'           => $name,
        'isin'           => $isin !== '' ? strtoupper($isin) : null,
        'currency'       => $currency,
        'inception_date' => $_POST['inception_date'] ?: null,
        'status'         => in_array($_POST['status'] ?? 'active', ['active','inactive'], true) ? $_POST['status'] : 'active',
        'display_order'  => (int)($_POST['display_order'] ?? 0),
    ];
    try {
        if ($scId > 0) {
            $db->update('share_classes', $data, ['id' => $scId]);
            AuditLog::log(Auth::userId(), 'share_class_updated', 'share_classes', $scId, $data['name']);
            flash('ok', 'Share class updated.');
        } else {
            $newId = $db->insert('share_classes', $data);
            AuditLog::log(Auth::userId(), 'share_class_created', 'share_classes', $newId, $data['name']);
            flash('ok', 'Share class added.');
        }
    } catch (\Throwable $e) {
        flash('error', 'Could not save: ' . $e->getMessage());
    }
    redirect(asset('admin/funds.php'));
}

// Delete share class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_sc') {
    Csrf::requireValid();
    $scId = (int)($_POST['id'] ?? 0);
    if ($scId > 0) {
        $sc = $db->fetchOne('SELECT * FROM share_classes WHERE id=:id', ['id'=>$scId]);
        if ($sc) {
            $db->delete('share_classes', ['id' => $scId]);
            AuditLog::log(Auth::userId(), 'share_class_deleted', 'share_classes', $scId, $sc['name']);
            flash('ok', 'Share class deleted.');
        }
    }
    redirect(asset('admin/funds.php'));
}

// Save fund
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_fund') {
    Csrf::requireValid();
    $fid = (int)($_POST['fund_id'] ?? 0);
    $data = [
        'slug'            => slugify($_POST['slug'] ?? $_POST['name_en']),
        'name_en'         => trim($_POST['name_en']),
        'name_de'         => trim($_POST['name_de']) ?: null,
        'description_en'  => trim($_POST['description_en']) ?: null,
        'description_de'  => trim($_POST['description_de']) ?: null,
        'objective_en'    => trim($_POST['objective_en']) ?: null,
        'objective_de'    => trim($_POST['objective_de']) ?: null,
        'launch_date'     => $_POST['launch_date'] ?: null,
        'base_currency'   => strtoupper(trim($_POST['base_currency'])) ?: null,
        'benchmark'       => trim($_POST['benchmark']) ?: null,
        'cover_image_path'=> trim($_POST['cover_image_path']) ?: null,
        'status'          => in_array($_POST['status'] ?? 'active', ['active','inactive'], true) ? $_POST['status'] : 'active',
        'display_order'   => (int)($_POST['display_order'] ?? 0),
    ];
    try {
        if ($fid > 0) {
            $db->update('funds', $data, ['id' => $fid]);
            AuditLog::log(Auth::userId(), 'fund_updated', 'funds', $fid, $data['name_en']);
            flash('ok', 'Fund updated.');
        } else {
            $fid = $db->insert('funds', $data);
            AuditLog::log(Auth::userId(), 'fund_created', 'funds', $fid, $data['name_en']);
            flash('ok', 'Fund created.');
        }
    } catch (\Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect(asset('admin/funds.php'));
}

$funds = $db->fetchAll('SELECT * FROM funds ORDER BY display_order');
$shareClasses = $db->fetchAll('SELECT * FROM share_classes ORDER BY fund_id, display_order');

$adminPage = ['title' => 'Funds & Share Classes', 'crumb' => 'Manage fund metadata and share class details'];
include __DIR__ . '/partials/layout-start.php';
?>

<?php if ($ok = flash('ok')): ?><div class="a-alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err = flash('error')): ?><div class="a-alert error"><?= e($err) ?></div><?php endif; ?>

<?php foreach ($funds as $fund): $scs = array_filter($shareClasses, fn($s) => $s['fund_id'] == $fund['id']); ?>
<div class="a-card" style="margin-bottom:22px;">
    <div class="a-card__head">
        <div>
            <h2><?= e($fund['name_en']) ?></h2>
            <div style="font-size:12px;color:var(--a-muted);margin-top:4px;">
                Launched <?= e(format_date($fund['launch_date'], 'M Y') ?: '—') ?> · Base <?= e($fund['base_currency']) ?> · <?= count($scs) ?> share classes
                <span class="a-badge <?= $fund['status']==='active'?'success':'muted' ?>" style="margin-left:8px;"><?= e($fund['status']) ?></span>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <a class="a-btn ghost sm" href="#sc-<?= e($fund['id']) ?>" onclick="document.getElementById('sc-modal-<?= e($fund['id']) ?>').classList.add('show')"><i class="fa-solid fa-plus"></i> Add share class</a>
            <a class="a-btn ghost sm" href="#fund-<?= e($fund['id']) ?>" onclick="document.getElementById('fund-modal-<?= e($fund['id']) ?>').classList.add('show')"><i class="fa-solid fa-pen"></i> Edit fund</a>
        </div>
    </div>
    <div class="a-card__body" style="padding:0;">
        <table class="a-table">
            <thead><tr><th>Class</th><th>ISIN</th><th>Currency</th><th>Inception</th><th>Status</th><th>Order</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($scs)): ?>
                <tr><td colspan="7" style="padding:20px;text-align:center;color:var(--a-muted);">No share classes yet.</td></tr>
                <?php else: foreach ($scs as $sc): ?>
                <tr>
                    <td><strong><?= e($sc['name']) ?></strong></td>
                    <td><code style="font-size:12px;color:var(--a-text-soft);"><?= e($sc['isin'] ?? '—') ?></code></td>
                    <td><?= e($sc['currency']) ?></td>
                    <td><small><?= e(format_date($sc['inception_date'], 'M Y') ?: '—') ?></small></td>
                    <td><span class="a-badge <?= $sc['status']==='active'?'success':'muted' ?>"><?= e($sc['status']) ?></span></td>
                    <td><?= e($sc['display_order']) ?></td>
                    <td style="text-align:right;">
                        <a class="a-btn ghost sm" href="#sc-<?= e($sc['id']) ?>" onclick="loadScForm(<?= e(json_encode($sc)) ?>,<?= e($fund['id']) ?>)"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete share class?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="del_sc">
                            <input type="hidden" name="id" value="<?= e($sc['id']) ?>">
                            <button class="a-btn danger sm" type="submit"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Fund edit modal -->
<div class="a-modal-backdrop" id="fund-modal-<?= e($fund['id']) ?>" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="a-modal" style="max-width:780px;">
        <div class="a-modal__head"><h3>Edit fund — <?= e($fund['name_en']) ?></h3><button class="a-modal__close" onclick="this.closest('.a-modal-backdrop').classList.remove('show')">&times;</button></div>
        <div class="a-modal__body">
            <form method="post" class="a-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_fund">
                <input type="hidden" name="fund_id" value="<?= e($fund['id']) ?>">
                <div class="row">
                    <div><label>Name (EN)</label><input type="text" name="name_en" required value="<?= e($fund['name_en']) ?>"></div>
                    <div><label>Name (DE)</label><input type="text" name="name_de" value="<?= e($fund['name_de'] ?? '') ?>"></div>
                </div>
                <label>Slug</label>
                <input type="text" name="slug" value="<?= e($fund['slug']) ?>">
                <div class="row">
                    <div><label>Launch date</label><input type="date" name="launch_date" value="<?= e($fund['launch_date']) ?>"></div>
                    <div><label>Base currency</label><input type="text" name="base_currency" maxlength="3" value="<?= e($fund['base_currency']) ?>"></div>
                    <div><label>Benchmark</label><input type="text" name="benchmark" value="<?= e($fund['benchmark']) ?>"></div>
                </div>
                <label>Description (EN)</label>
                <textarea name="description_en" rows="3"><?= e($fund['description_en']) ?></textarea>
                <label>Description (DE)</label>
                <textarea name="description_de" rows="3"><?= e($fund['description_de'] ?? '') ?></textarea>
                <label>Objective (EN)</label>
                <textarea name="objective_en" rows="3"><?= e($fund['objective_en']) ?></textarea>
                <label>Objective (DE)</label>
                <textarea name="objective_de" rows="3"><?= e($fund['objective_de'] ?? '') ?></textarea>
                <div class="row">
                    <div><label>Cover image path</label><input type="text" name="cover_image_path" value="<?= e($fund['cover_image_path']) ?>"></div>
                    <div><label>Status</label><select name="status"><option <?= $fund['status']==='active'?'selected':'' ?>>active</option><option <?= $fund['status']==='inactive'?'selected':'' ?>>inactive</option></select></div>
                    <div><label>Order</label><input type="number" name="display_order" value="<?= e($fund['display_order']) ?>"></div>
                </div>
                <button class="a-btn lg" type="submit" style="margin-top:18px;">Save fund</button>
            </form>
        </div>
    </div>
</div>

<!-- Add share class modal -->
<div class="a-modal-backdrop" id="sc-modal-<?= e($fund['id']) ?>" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="a-modal">
        <div class="a-modal__head"><h3>Add share class</h3><button class="a-modal__close" onclick="this.closest('.a-modal-backdrop').classList.remove('show')">&times;</button></div>
        <div class="a-modal__body">
            <form method="post" class="a-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="save_sc">
                <input type="hidden" name="sc_id" value="">
                <input type="hidden" name="fund_id" value="<?= e($fund['id']) ?>">
                <div class="row">
                    <div><label>Name *</label><input type="text" name="name" required placeholder="Class A EUR"></div>
                    <div><label>Currency *</label><input type="text" name="currency" required maxlength="3" placeholder="EUR"></div>
                </div>
                <label>ISIN</label>
                <input type="text" name="isin" maxlength="20">
                <div class="row">
                    <div><label>Inception</label><input type="date" name="inception_date"></div>
                    <div><label>Order</label><input type="number" name="display_order" value="0"></div>
                    <div><label>Status</label><select name="status"><option value="active">active</option><option value="inactive">inactive</option></select></div>
                </div>
                <button class="a-btn lg" type="submit" style="margin-top:18px;">Save share class</button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="a-card">
    <div class="a-card__head"><h2>Add a new fund</h2></div>
    <div class="a-card__body">
        <form method="post" class="a-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save_fund">
            <input type="hidden" name="fund_id" value="">
            <div class="row">
                <div><label>Name (EN) *</label><input type="text" name="name_en" required></div>
                <div><label>Slug</label><input type="text" name="slug" placeholder="auto from name"></div>
                <div><label>Base currency</label><input type="text" name="base_currency" maxlength="3"></div>
            </div>
            <button class="a-btn" type="submit" style="margin-top:14px;">Create fund</button>
        </form>
    </div>
</div>

<script>
function loadScForm(sc, fundId) {
    var modal = document.getElementById('sc-modal-'+fundId);
    var form = modal.querySelector('form');
    form.querySelector('[name=sc_id]').value = sc.id;
    form.querySelector('[name=name]').value = sc.name;
    form.querySelector('[name=currency]').value = sc.currency;
    form.querySelector('[name=isin]').value = sc.isin || '';
    form.querySelector('[name=inception_date]').value = sc.inception_date || '';
    form.querySelector('[name=display_order]').value = sc.display_order;
    form.querySelector('[name=status]').value = sc.status;
    modal.classList.add('show');
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
