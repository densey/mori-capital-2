<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\setting;
use function Mori\safe_url;
use function Mori\t;

try {
    $db = Database::instance(); $team = $db->fetchAll('SELECT * FROM team_members WHERE status="active" ORDER BY display_order'); }
catch (\Throwable) { $team = []; }

$page = [
    'title'       => t('page.team.title'),
    'description' => t('page.team.desc'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.team')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<div class="our-services" style="padding:70px 0;">
    <div class="container">
        <?php foreach ($team as $i => $member):
            $photoSrc = $member['photo_path'] ?? '';
            if (!preg_match('/^https?:\/\//', $photoSrc) && $photoSrc !== '') $photoSrc = asset($photoSrc);
            $bio = (string) (I18n::fieldFor($member, 'bio') ?? '');
            // Bios are entered via the WYSIWYG editor, so they may contain HTML
            // (<p>, <br>, …). Render that as-is; fall back to nl2br for older
            // plain-text rows so their line breaks still show.
            $bioOut = ($bio !== strip_tags($bio)) ? $bio : nl2br(e($bio));
            $flip = $i % 2 === 1;
        ?>
        <div id="<?= e($member['slug']) ?>" class="row align-items-center wow fadeInUp" style="margin-bottom:60px;flex-direction:<?= $flip?'row-reverse':'row' ?>;">
            <div class="col-md-4">
                <figure style="aspect-ratio:1;border-radius:12px;overflow:hidden;background:var(--mori-bg-soft,#F5F7FA);margin:0;">
                    <img src="<?= e($photoSrc) ?>" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($member['name']) ?>&background=1B3A5C&color=fff&size=600&bold=true&font-size=0.36'" alt="<?= e($member['name']) ?>" style="width:100%;height:100%;object-fit:cover;filter:grayscale(0.1);">
                </figure>
            </div>
            <div class="col-md-8 team-bio-col" style="padding:<?= $flip?'0 clamp(15px,4vw,50px) 0 clamp(15px,3vw,30px)':'0 clamp(15px,3vw,30px) 0 clamp(15px,4vw,50px)' ?>;">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.14em;color:var(--accent-color,#1ABC9C);font-weight:700;margin-bottom:8px;"><?= e(I18n::fieldFor($member, 'title')) ?></div>
                <h2 style="font-size:clamp(22px,2.4vw,30px);margin-bottom:14px;"><?= e($member['name']) ?></h2>
                <div style="height:2px;width:60px;background:var(--mori-border,#E1E7EE);margin-bottom:18px;"></div>
                <div class="team-bio-text" style="font-size:14.5px;line-height:1.75;color:var(--mori-text-soft,#5A6B7B);"><?= $bioOut ?></div>
                <?php if (!empty($member['linkedin_url']) || !empty($member['email'])): ?>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <?php if (!empty($member['linkedin_url'])): ?>
                    <a href="<?= e(safe_url($member['linkedin_url'] ?? null)) ?>" style="display:inline-flex;width:36px;height:36px;border-radius:50%;background:var(--mori-bg-tint,#EEF3F8);color:var(--primary-color,#1B3A5C);align-items:center;justify-content:center;text-decoration:none;"><i class="fa-brands fa-linkedin-in"></i></a>
                    <?php endif; ?>
                    <a href="mailto:<?= e($member['email'] ?: setting('contact_email')) ?>" style="display:inline-flex;width:36px;height:36px;border-radius:50%;background:var(--mori-bg-tint,#EEF3F8);color:var(--primary-color,#1B3A5C);align-items:center;justify-content:center;text-decoration:none;"><i class="fa-regular fa-envelope"></i></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
