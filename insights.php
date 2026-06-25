<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\t;

try {
    $db = Database::instance();
    $where = ['status = "published"', 'locale = :loc'];
    $params = ['loc' => I18n::locale()];
    if (!empty($_GET['cat'])) {
        $where[] = 'category = :c'; $params['c'] = (string)$_GET['cat'];
    }
    $insights = $db->fetchAll('SELECT * FROM insights WHERE ' . implode(' AND ', $where) . ' ORDER BY publish_date DESC', $params);
} catch (\Throwable) { $insights = []; }

$categories = [
    ''                    => 'All',
    'outlook'             => 'Outlook',
    'factsheet'           => 'Factsheet',
    'shareholder_notice'  => 'Shareholder Notice',
    'article'             => 'Article',
    'press'               => 'Press',
];

$page = [
    'title'       => 'Mori Views — ' . t('page.insights.subtitle'),
    'description' => 'Quarterly outlooks, fund factsheets and shareholder communications.',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.insights')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:60px 0;">
    <div class="container">
        <!-- Category pills -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:30px;">
            <?php foreach ($categories as $key => $label):
                $active = ($_GET['cat'] ?? '') === $key;
            ?>
            <a href="<?= asset('insights.php' . ($key ? '?cat=' . urlencode($key) : '')) ?>" style="padding:8px 16px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid var(--mori-border,#E1E7EE);<?= $active?'background:var(--accent-color,#1ABC9C);color:#fff;border-color:var(--accent-color,#1ABC9C);':'background:#fff;color:var(--mori-text-soft,#5A6B7B);' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($insights)): ?>
            <div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
                No insights published yet.<?php if (\Mori\Auth::check()): ?> Add from the <a href="<?= asset('admin/insights.php') ?>" style="color:var(--accent-color,#1ABC9C);">admin panel</a>.<?php endif; ?>
            </div>
        <?php else: ?>
        <div class="mori-insights" style="background:transparent;padding:0;">
            <div class="row">
                <?php foreach ($insights as $i => $ins): ?>
                <div class="col-xl-4 col-md-6" style="margin-bottom:24px;">
                    <a href="<?= asset('insight.php?slug=' . e($ins['slug'])) ?>" class="insight-card wow fadeInUp" <?= $i%3>0?'data-wow-delay="0.'.($i%3).'s"':'' ?>>
                        <div class="insight-meta">
                            <span><?= e(ucwords(str_replace('_',' ', $ins['category']))) ?></span>
                            <span class="date"><?= e(format_date($ins['publish_date'])) ?></span>
                        </div>
                        <h3><?= e($ins['title']) ?></h3>
                        <p><?= e($ins['excerpt']) ?></p>
                        <span class="insight-link"><?= e(t('btn.read_more')) ?> <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
