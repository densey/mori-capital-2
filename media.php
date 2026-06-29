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
    $items = $db->fetchAll(
        'SELECT * FROM media_items WHERE status = "published" ORDER BY display_order ASC, item_date DESC'
    );
} catch (\Throwable) {
    $items = [];
}

$loc = I18n::locale();

// Locale-aware field pickers
$mTitle = function (array $it) use ($loc): string {
    if ($loc === 'de' && trim((string)($it['title_de'] ?? '')) !== '') return (string)$it['title_de'];
    return (string)($it['title_en'] ?? '');
};
$mUrl = function (array $it) use ($loc): string {
    if ($loc === 'de' && trim((string)($it['url_de'] ?? '')) !== '') return (string)$it['url_de'];
    return (string)($it['url_en'] ?? '');
};

// Type → label + action verb + icon
$typeMeta = [
    'press_release' => ['label' => t('media.type.press_release'), 'action' => t('media.action.view'),   'icon' => 'fa-regular fa-file-pdf'],
    'article'       => ['label' => t('media.type.article'),       'action' => t('media.action.read'),   'icon' => 'fa-solid fa-arrow-up-right-from-square'],
    'video'         => ['label' => t('media.type.video'),         'action' => t('media.action.watch'),  'icon' => 'fa-brands fa-youtube'],
    'podcast'       => ['label' => t('media.type.podcast'),       'action' => t('media.action.listen'), 'icon' => 'fa-solid fa-headphones'],
];

// Group by year (newest first)
$byYear = [];
foreach ($items as $it) {
    $y = !empty($it['item_date']) ? substr((string)$it['item_date'], 0, 4) : '—';
    $byYear[$y][] = $it;
}
krsort($byYear);

$page = [
    'title'       => t('page.media.title'),
    'description' => t('page.media.desc'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.media')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:60px 0;">
    <div class="container">
        <div class="row section-row align-items-end" style="margin-bottom:10px;">
            <div class="col-xl-8">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp"><?= e(t('media.eyebrow')) ?></span>
                    <h2 style="font-size:clamp(22px,2.4vw,30px);"><?= e(t('media.heading')) ?></h2>
                </div>
            </div>
        </div>
        <p style="max-width:760px;font-size:15px;line-height:1.7;color:var(--mori-text-soft,#5A6B7B);margin-bottom:36px;"><?= e(t('media.intro')) ?></p>

        <?php if (empty($items)): ?>
            <div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
                <?= e(t('media.empty')) ?>
            </div>
        <?php else: ?>
            <?php foreach ($byYear as $year => $group): ?>
            <div style="margin-bottom:34px;">
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                    <h3 style="font-size:18px;color:var(--primary-color,#1B3A5C);margin:0;letter-spacing:0.02em;"><?= e($year) ?></h3>
                    <div style="flex:1;height:1px;background:var(--mori-border,#E1E7EE);"></div>
                </div>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <?php foreach ($group as $it):
                        $meta  = $typeMeta[$it['item_type']] ?? $typeMeta['article'];
                        $url   = $mUrl($it);
                        $title = $mTitle($it);
                        $ext   = (int)($it['is_external'] ?? 1) === 1;
                        // Local PDF paths get the asset() prefix; external links pass through.
                        $href  = preg_match('#^https?://#', $url) ? $url : asset($url);
                        $target = $ext ? ' target="_blank" rel="noopener noreferrer"' : ' target="_blank" rel="noopener"';
                    ?>
                    <a href="<?= e($href) ?>"<?= $target ?> class="media-row" style="display:flex;align-items:center;gap:18px;background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:10px;padding:18px 22px;text-decoration:none;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease;">
                        <div style="width:44px;height:44px;border-radius:8px;background:rgba(26,188,156,.12);color:var(--accent-color,#1ABC9C);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;">
                            <i class="<?= e($meta['icon']) ?>"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:4px;">
                                <span style="font-size:10px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--accent-color,#1ABC9C);background:rgba(26,188,156,.10);padding:2px 8px;border-radius:999px;"><?= e($meta['label']) ?></span>
                                <span style="font-size:12px;color:var(--mori-muted,#7A8B99);"><?= e($it['item_date'] ? format_date($it['item_date']) : '') ?><?php if (!empty($it['source'])): ?> &middot; <?= e($it['source']) ?><?php endif; ?></span>
                            </div>
                            <div style="font-size:15px;font-weight:600;color:var(--primary-color,#1B3A5C);line-height:1.4;"><?= e($title) ?></div>
                        </div>
                        <div style="flex-shrink:0;display:inline-flex;align-items:center;gap:7px;color:var(--accent-color,#1ABC9C);font-size:13px;font-weight:600;white-space:nowrap;">
                            <span class="media-row__action"><?= e($meta['action']) ?></span>
                            <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.media-row:hover { border-color: var(--accent-color,#1ABC9C) !important; box-shadow: 0 6px 18px rgba(8,18,33,.07); transform: translateY(-1px); }
@media (max-width: 575px) {
    .media-row { flex-wrap: wrap; gap: 12px !important; }
    .media-row .media-row__action { display: none; }
}
</style>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
