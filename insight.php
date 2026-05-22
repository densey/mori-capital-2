<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\t;

$slug = (string)($_GET['slug'] ?? '');
$db = Database::instance();
try {
    $ins = $db->fetchOne(
        'SELECT * FROM insights WHERE slug = :s AND locale = :loc AND status = "published"',
        ['s' => $slug, 'loc' => I18n::locale()]
    );
    if ($ins) {
        $db->query('UPDATE insights SET view_count = view_count + 1 WHERE id = :id', ['id' => $ins['id']]);
    }
} catch (\Throwable) { $ins = null; }

if (!$ins) {
    http_response_code(404);
    $page = ['title' => 'Insight not found'];
    include __DIR__ . '/src/partials/head.php';
    include __DIR__ . '/src/partials/topbar.php';
    include __DIR__ . '/src/partials/header.php';
    echo '<div class="container" style="padding:120px 0;text-align:center;"><h1>Insight not found</h1><p><a href="' . asset('insights.php') . '">Back to all Mori Views</a></p></div>';
    include __DIR__ . '/src/partials/footer.php';
    include __DIR__ . '/src/partials/scripts.php';
    exit;
}

$page = [
    'title'       => $ins['title'] . ' — Mori Views',
    'description' => $ins['excerpt'] ?? '',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.insights'), 'url' => asset('insights.php')],
        ['label' => $ins['title']],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<article class="our-services" style="padding:60px 0;">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div style="margin-bottom:30px;font-size:12px;text-transform:uppercase;letter-spacing:0.12em;color:var(--accent-color,#1ABC9C);font-weight:700;">
                    <?= e(ucwords(str_replace('_',' ', $ins['category']))) ?> · <span style="color:var(--mori-muted,#7A8B99);font-weight:500;letter-spacing:0.05em;text-transform:none;"><?= e(format_date($ins['publish_date'])) ?></span>
                </div>
                <h1 style="font-size:clamp(28px,3.4vw,40px);line-height:1.18;margin-bottom:24px;letter-spacing:-0.01em;"><?= e($ins['title']) ?></h1>

                <?php if (!empty($ins['cover_image_path'])): ?>
                <figure style="margin:30px 0;border-radius:12px;overflow:hidden;">
                    <img src="<?= asset(e($ins['cover_image_path'])) ?>" alt="<?= e($ins['title']) ?>" style="width:100%;display:block;">
                </figure>
                <?php endif; ?>

                <div class="legal-content" style="font-size:16px;line-height:1.8;color:var(--mori-text-soft,#5A6B7B);">
                    <?= $ins['body'] ?>
                </div>

                <div style="margin-top:40px;padding-top:24px;border-top:1px solid var(--mori-border,#E1E7EE);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
                    <a href="<?= asset('insights.php') ?>" style="color:var(--accent-color,#1ABC9C);font-weight:600;font-size:14px;text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Back to all Mori Views</a>
                    <div style="font-size:12px;color:var(--mori-muted,#7A8B99);">
                        Share: <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode((string)\Mori\url($_SERVER['REQUEST_URI'])) ?>" target="_blank" rel="noopener" style="color:var(--accent-color,#1ABC9C);"><i class="fa-brands fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</article>

<style>
.legal-content h2 { color: var(--primary-color, #1B3A5C); font-size: 22px; margin: 32px 0 14px; }
.legal-content h3 { color: var(--primary-color, #1B3A5C); font-size: 18px; margin: 24px 0 10px; }
.legal-content p { margin-bottom: 16px; }
.legal-content blockquote { border-left: 3px solid var(--accent-color, #1ABC9C); padding-left: 20px; margin: 24px 0; font-style: italic; color: var(--primary-color, #1B3A5C); }
.legal-content ul, .legal-content ol { padding-left: 24px; margin-bottom: 18px; }
.legal-content li { margin-bottom: 6px; }
</style>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
