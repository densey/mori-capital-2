<?php
require __DIR__ . '/../src/bootstrap.php';
use Mori\Auth;
use Mori\AuditLog;
use function Mori\e;
use function Mori\format_date;

Auth::requireRole('super_admin');
$entries = AuditLog::recent(300);

// Collect unique action types for the dropdown
$actionTypes = [];
foreach ($entries as $a) {
    $act = $a['action'] ?? '';
    if ($act !== '' && !in_array($act, $actionTypes, true)) {
        $actionTypes[] = $act;
    }
}
sort($actionTypes);

// Read filter values from query string (used by JS to restore state on page load)
$filterSearch = $_GET['q'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterFrom   = $_GET['from'] ?? '';
$filterTo     = $_GET['to'] ?? '';

$adminPage = ['title' => 'Audit Log', 'crumb' => 'Last 300 admin actions'];
include __DIR__ . '/partials/layout-start.php';
?>

<!-- Audit log filters -->
<div class="a-card" style="margin-bottom:18px;">
    <div class="a-card__body a-form" style="display:flex;gap:14px;flex-wrap:wrap;align-items:end;">
        <div style="flex:2;min-width:180px;">
            <label for="audit-search">Search</label>
            <input type="text" id="audit-search" placeholder="Filter by description, details, user..." value="<?= e($filterSearch) ?>">
        </div>
        <div style="flex:1;min-width:150px;">
            <label for="audit-action">Action type</label>
            <select id="audit-action">
                <option value="">All actions</option>
                <?php foreach ($actionTypes as $act): ?>
                <option value="<?= e($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $act)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:140px;">
            <label for="audit-from">From</label>
            <input type="date" id="audit-from" value="<?= e($filterFrom) ?>">
        </div>
        <div style="flex:1;min-width:140px;">
            <label for="audit-to">To</label>
            <input type="date" id="audit-to" value="<?= e($filterTo) ?>">
        </div>
        <div>
            <button type="button" class="a-btn ghost sm" id="audit-clear" title="Clear filters">Clear</button>
        </div>
    </div>
</div>

<div class="a-card">
    <div class="a-card__body" style="padding:0;">
        <table class="a-table" id="audit-table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($entries as $a): ?>
                <tr data-action="<?= e($a['action'] ?? '') ?>" data-date="<?= e(date('Y-m-d', strtotime($a['created_at']))) ?>">
                    <td><small><?= e(format_date($a['created_at'], 'd M Y · H:i:s')) ?></small></td>
                    <td><strong><?= e($a['user_name'] ?? 'System') ?></strong><br><small><?= e($a['user_email'] ?? '') ?></small></td>
                    <td><span class="a-badge muted"><?= e(str_replace('_',' ', $a['action'])) ?></span></td>
                    <td><?= e($a['entity'] ?? '') ?><?= $a['entity_id'] ? ' #' . e($a['entity_id']) : '' ?></td>
                    <td><small style="color:var(--a-text-soft);"><?= e($a['details'] ?? '') ?></small></td>
                    <td><small style="font-family:monospace;color:var(--a-muted);"><?= e($a['ip_address']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    var search  = document.getElementById('audit-search');
    var action  = document.getElementById('audit-action');
    var dateFrom = document.getElementById('audit-from');
    var dateTo   = document.getElementById('audit-to');
    var clear    = document.getElementById('audit-clear');
    var rows     = document.querySelectorAll('#audit-table tbody tr');

    function filterRows() {
        var q   = search.value.toLowerCase();
        var act = action.value;
        var from = dateFrom.value;
        var to   = dateTo.value;

        rows.forEach(function(row) {
            var text    = row.textContent.toLowerCase();
            var rowAct  = row.getAttribute('data-action');
            var rowDate = row.getAttribute('data-date');

            var matchText   = !q || text.indexOf(q) !== -1;
            var matchAction = !act || rowAct === act;
            var matchFrom   = !from || rowDate >= from;
            var matchTo     = !to || rowDate <= to;

            row.style.display = (matchText && matchAction && matchFrom && matchTo) ? '' : 'none';
        });
    }

    search.addEventListener('input', filterRows);
    action.addEventListener('change', filterRows);
    dateFrom.addEventListener('change', filterRows);
    dateTo.addEventListener('change', filterRows);

    clear.addEventListener('click', function() {
        search.value = '';
        action.value = '';
        dateFrom.value = '';
        dateTo.value = '';
        filterRows();
    });

    // Apply filters on load if query params are present
    if (search.value || action.value || dateFrom.value || dateTo.value) {
        filterRows();
    }
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
