<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use function Mori\e;
use function Mori\asset;
use function Mori\t;

try {
    $db = Database::instance();
    $funds = $db->fetchAll('SELECT * FROM funds WHERE status = "active" ORDER BY display_order');
    $selectedFundId = isset($_GET['fund']) ? (int)$_GET['fund'] : ($funds[0]['id'] ?? 0);
    $shareClasses = $selectedFundId ? $db->fetchAll('SELECT * FROM share_classes WHERE fund_id = :id ORDER BY display_order', ['id' => $selectedFundId]) : [];
    $selectedScId = isset($_GET['class']) ? (int)$_GET['class'] : ($shareClasses[0]['id'] ?? 0);
    $navData = $selectedScId ? $db->fetchAll(
        'SELECT entry_date, nav, benchmark_value FROM nav_entries WHERE share_class_id = :id ORDER BY entry_date ASC',
        ['id' => $selectedScId]
    ) : [];
} catch (\Throwable) {
    $funds = []; $shareClasses = []; $navData = []; $selectedFundId = 0; $selectedScId = 0;
}

$page = [
    'title' => t('page.fund_performance.title') . ' — Mori Capital',
    'description' => 'Interactive performance chart and NAV history for Mori funds and share classes.',
    'breadcrumb' => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => 'Performance'],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:60px 0;">
    <div class="container">
        <!-- Selectors -->
        <form method="get" style="display:flex;gap:16px;flex-wrap:wrap;align-items:end;background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:22px 24px;margin-bottom:30px;">
            <div style="flex:1;min-width:0;">
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;"><?= e(t('nav.funds')) ?></label>
                <select name="fund" onchange="this.form.submit()" style="width:100%;padding:10px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;">
                    <?php foreach ($funds as $f): ?>
                        <option value="<?= e($f['id']) ?>" <?= $selectedFundId==$f['id']?'selected':'' ?>><?= e($f['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:0;">
                <label style="display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;"><?= e(t('doc.share_class')) ?></label>
                <select name="class" onchange="this.form.submit()" style="width:100%;padding:10px 12px;border:1px solid var(--mori-border,#E1E7EE);border-radius:6px;font-family:inherit;font-size:14px;">
                    <?php foreach ($shareClasses as $sc): ?>
                        <option value="<?= e($sc['id']) ?>" <?= $selectedScId==$sc['id']?'selected':'' ?>>
                            <?= e($sc['name']) ?> — <?= e($sc['currency']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <!-- Chart card -->
        <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:14px;padding:30px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:4px;">NAV evolution</div>
                    <?php
                        $selectedFund = null;
                        foreach ($funds as $f) { if ($f['id'] == $selectedFundId) { $selectedFund = $f; break; } }
                    ?>
                    <h2 style="font-size:20px;margin:0;"><?= e($selectedFund['name_en'] ?? '—') ?></h2>
                </div>
                <div style="display:flex;gap:4px;background:var(--mori-bg-soft,#F5F7FA);padding:4px;border-radius:999px;font-size:12px;font-weight:600;">
                    <button type="button" class="rng" data-range="1m" style="padding:6px 12px;border:none;background:transparent;border-radius:999px;cursor:pointer;color:var(--mori-text-soft,#5A6B7B);">1M</button>
                    <button type="button" class="rng" data-range="3m" style="padding:6px 12px;border:none;background:transparent;border-radius:999px;cursor:pointer;color:var(--mori-text-soft,#5A6B7B);">3M</button>
                    <button type="button" class="rng" data-range="6m" style="padding:6px 12px;border:none;background:transparent;border-radius:999px;cursor:pointer;color:var(--mori-text-soft,#5A6B7B);">6M</button>
                    <button type="button" class="rng active" data-range="1y" style="padding:6px 12px;border:none;background:var(--accent-color,#1ABC9C);border-radius:999px;cursor:pointer;color:#fff;">1Y</button>
                    <button type="button" class="rng" data-range="3y" style="padding:6px 12px;border:none;background:transparent;border-radius:999px;cursor:pointer;color:var(--mori-text-soft,#5A6B7B);">3Y</button>
                    <button type="button" class="rng" data-range="5y" style="padding:6px 12px;border:none;background:transparent;border-radius:999px;cursor:pointer;color:var(--mori-text-soft,#5A6B7B);">5Y</button>
                    <button type="button" class="rng" data-range="max" style="padding:6px 12px;border:none;background:transparent;border-radius:999px;cursor:pointer;color:var(--mori-text-soft,#5A6B7B);">Max</button>
                </div>
            </div>
            <div id="perfChart" style="width:100%;height:clamp(260px,50vw,420px);"></div>
            <?php if (empty($navData)): ?>
            <p style="text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;padding:80px 0;">No NAV data available yet for this share class.<?php if (\Mori\Auth::check()): ?><br>Add monthly NAV entries from <a href="<?= asset('admin/performance.php') ?>" style="color:var(--accent-color,#1ABC9C);">the admin panel</a>.<?php endif; ?></p>
            <?php endif; ?>
        </div>

        <!-- Returns table -->
        <?php if (!empty($navData)):
            // Compute YTD / 1Y / 3Y / 5Y returns from latest NAV
            $latest = end($navData);
            function findNavAt($data, $date) {
                $closest = null;
                foreach ($data as $row) {
                    if ($row['entry_date'] <= $date) $closest = $row;
                    else break;
                }
                return $closest;
            }
            $today = new \DateTimeImmutable($latest['entry_date']);
            $ranges = [
                'YTD' => $today->modify('first day of January')->format('Y-m-d'),
                '1Y'  => $today->modify('-1 year')->format('Y-m-d'),
                '3Y'  => $today->modify('-3 years')->format('Y-m-d'),
                '5Y'  => $today->modify('-5 years')->format('Y-m-d'),
                '10Y' => $today->modify('-10 years')->format('Y-m-d'),
            ];
        ?>
        <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:14px;padding:30px;margin-top:24px;">
            <h3 style="font-size:18px;margin-bottom:18px;">Cumulative returns (as of <?= e(\Mori\format_date($latest['entry_date'])) ?>)</h3>
            <div class="perf-returns-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;">
                <?php foreach ($ranges as $label => $cutoffDate):
                    $startRow = findNavAt($navData, $cutoffDate);
                    $ret = $startRow ? (($latest['nav'] - $startRow['nav']) / $startRow['nav']) * 100 : null;
                    $color = $ret === null ? 'var(--mori-muted,#7A8B99)' : ($ret >= 0 ? '#16A085' : '#C0392B');
                ?>
                <div style="background:var(--mori-bg-soft,#F5F7FA);border-radius:10px;padding:18px 20px;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:var(--mori-muted,#7A8B99);font-weight:600;margin-bottom:6px;"><?= e($label) ?></div>
                    <div style="font-size:18px;font-weight:700;color:<?= $color ?>;"><?= $ret !== null ? ($ret >= 0 ? '+' : '') . number_format($ret, 2) . '%' : '—' ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
?>

<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
var navData = <?= json_encode(array_map(fn($r) => ['x' => $r['entry_date'], 'y' => (float)$r['nav']], $navData)) ?>;
var benchData = <?= json_encode(array_values(array_filter(array_map(fn($r) => $r['benchmark_value'] !== null ? ['x' => $r['entry_date'], 'y' => (float)$r['benchmark_value']] : null, $navData)))) ?>;
var showBenchmark = <?= \Mori\setting('show_benchmark', '1') === '1' ? 'true' : 'false' ?>;
if (navData.length > 0) {
    var series = [{name: 'NAV', data: navData}];
    if (showBenchmark && benchData.length > 0) series.push({name: 'Benchmark', data: benchData});
    var options = {
        chart: { type: 'area', height: 420, fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: true } },
        colors: ['#1ABC9C', '#1B3A5C'],
        series: series,
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.02, stops: [0, 95] } },
        xaxis: { type: 'datetime', labels: { style: { colors: '#7A8B99', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#7A8B99', fontSize: '11px' }, formatter: v => v.toFixed(2) } },
        grid: { borderColor: '#E1E7EE', strokeDashArray: 3 },
        tooltip: { x: { format: 'MMM yyyy' }, y: { formatter: v => v.toFixed(4) } },
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '13px', labels: { colors: '#5A6B7B' } },
    };
    var chart = new ApexCharts(document.querySelector('#perfChart'), options);
    chart.render();

    // Range pills
    document.querySelectorAll('.rng').forEach(btn => btn.addEventListener('click', function () {
        document.querySelectorAll('.rng').forEach(b => { b.classList.remove('active'); b.style.background='transparent'; b.style.color='#5A6B7B'; });
        this.classList.add('active'); this.style.background='#1ABC9C'; this.style.color='#fff';
        var range = this.dataset.range;
        var now = new Date(navData[navData.length - 1].x);
        var from;
        switch (range) {
            case '1m': from = new Date(now); from.setMonth(now.getMonth()-1); break;
            case '3m': from = new Date(now); from.setMonth(now.getMonth()-3); break;
            case '6m': from = new Date(now); from.setMonth(now.getMonth()-6); break;
            case '1y': from = new Date(now); from.setFullYear(now.getFullYear()-1); break;
            case '3y': from = new Date(now); from.setFullYear(now.getFullYear()-3); break;
            case '5y': from = new Date(now); from.setFullYear(now.getFullYear()-5); break;
            default:   from = new Date(navData[0].x);
        }
        chart.zoomX(from.getTime(), now.getTime());
    }));
}
</script>

<?php include __DIR__ . '/src/partials/scripts.php'; ?>
