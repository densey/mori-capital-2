<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\format_date;
use function Mori\t;

$announcements = [];
$curLocale = I18n::locale();

try {
    $db = Database::instance();
    $announcements = $db->fetchAll(
        'SELECT a.*, f.name_en AS fund_name_en, f.name_de AS fund_name_de,
                d.id AS doc_id, d.title AS doc_title, d.file_path AS doc_path
           FROM fund_announcements a
           LEFT JOIN funds f ON f.id = a.fund_id
           LEFT JOIN documents d ON d.id = a.document_id
          WHERE a.status = "published"
            AND (a.locale = :loc OR a.locale = "any")
          ORDER BY a.publish_date DESC, a.id DESC',
        ['loc' => $curLocale]
    );
} catch (\Throwable) {}

$page = [
    'title'       => t('nav.announcements') . ' · Mori Capital',
    'description' => t('ann.intro'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.announcements')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:50px 0;">
    <div class="container">
        <div style="margin-bottom:24px;max-width:780px;">
            <span style="font-size:12px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--accent-color,#1ABC9C);"><?= e(t('ann.eyebrow')) ?></span>
            <h2 style="font-size:clamp(24px,2.6vw,32px);margin:6px 0 8px;"><?= e(t('nav.announcements')) ?></h2>
            <p style="font-size:14.5px;color:var(--mori-text-soft,#5A6B7B);line-height:1.6;margin:0;">
                <?= e(t('ann.intro')) ?>
            </p>
        </div>

        <?php if (empty($announcements)): ?>
        <div style="background:#fff;border:1px dashed var(--mori-border,#E1E7EE);border-radius:10px;padding:48px;text-align:center;color:var(--mori-muted,#7A8B99);font-size:14px;">
            <?= e(t('ann.empty')) ?>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($announcements as $a):
            $fundName = $a['fund_id'] ? I18n::fieldFor($a, 'fund_name') : null;
        ?>
            <article style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:12px;padding:26px 28px;">
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:10px;">
                    <span style="font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--accent-color,#1ABC9C);"><?= e(format_date($a['publish_date'])) ?></span>
                    <?php if ($fundName): ?>
                    <span style="font-size:11px;font-weight:600;background:var(--mori-bg-tint,#EEF3F8);color:var(--primary-color,#1B3A5C);padding:3px 10px;border-radius:999px;"><?= e($fundName) ?></span>
                    <?php endif; ?>
                </div>
                <h3 style="font-size:20px;color:var(--primary-color,#1B3A5C);margin:0 0 10px;line-height:1.3;"><?= e($a['title']) ?></h3>
                <?php if (!empty($a['body'])): ?>
                <div style="font-size:14.5px;color:var(--mori-text-soft,#5A6B7B);line-height:1.7;">
                    <?= $a['body'] ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($a['doc_id'])): ?>
                <div style="margin-top:14px;">
                    <a href="<?= asset('api/download.php?id=' . (int)$a['doc_id']) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:8px;background:var(--accent-color,#1ABC9C);color:#fff;padding:9px 16px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;">
                        <i class="fa-regular fa-file-pdf"></i> <?= e($a['doc_title']) ?>
                    </a>
                </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
?>
