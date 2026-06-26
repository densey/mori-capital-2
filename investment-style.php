<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\t;

try {
    $db = Database::instance();
    $pageData = $db->fetchOne(
        'SELECT * FROM pages WHERE slug = "investment-style" AND locale = :loc AND status = "published"',
        ['loc' => I18n::locale()]
    ) ?? $db->fetchOne('SELECT * FROM pages WHERE slug = "investment-style" AND locale = "en"');
} catch (\Throwable) { $pageData = null; }

$page = [
    'title'       => ($pageData['meta_title'] ?? null) ?: t('page.investment_style.title'),
    'description' => ($pageData['meta_description'] ?? null) ?: t('page.investment_style.desc'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.investment_style')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<!-- Philosophy -->
<div class="why-choose-us" style="padding:80px 0;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-xl-6">
                <div class="why-choose-us-content">
                    <div class="section-title">
                        <span class="section-sub-title wow fadeInUp"><?= e(t('style.eyebrow')) ?></span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e(t('section.style.title')) ?></h2>
                        <p class="wow fadeInUp" data-wow-delay="0.2s"><?= e(t('style.philosophy_lead')) ?></p>
                    </div>
                    <div class="wow fadeInUp" data-wow-delay="0.3s">
                        <?= $pageData['body'] ?? '' ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="why-choose-us-image-box wow fadeInUp" data-wow-delay="0.2s">
                    <div class="why-choose-us-image">
                        <figure class="image-anime">
                            <img src="<?= asset('assets/images/hero/hero-corporate-2.jpg') ?>" alt="<?= e(t('style.image_alt')) ?>">
                        </figure>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5 principles -->
<div class="our-services" style="background:var(--mori-bg-soft,#F5F7FA);padding:80px 0;">
    <div class="container">
        <div class="row section-row align-items-end">
            <div class="col-xl-6">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp"><?= e(t('style.principles_eyebrow')) ?></span>
                    <h2><?= e(t('style.principles_heading')) ?></h2>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top:24px;">
            <?php
            $principles = [];
            for ($pi = 1; $pi <= 5; $pi++) {
                $icon  = \Mori\setting("principle_{$pi}_icon");
                $title = \Mori\setting_i18n("principle_{$pi}_title");
                $desc  = \Mori\setting_i18n("principle_{$pi}_desc");
                if ($title) $principles[] = [$icon ?: 'fa-circle-check', $title, $desc ?: ''];
            }
            if (empty($principles)) {
                $principles = [
                    ['fa-magnifying-glass-chart', t('style.fallback.p1_title'), t('style.fallback.p1_desc')],
                    ['fa-route',                  t('style.fallback.p2_title'), t('style.fallback.p2_desc')],
                    ['fa-flask',                  t('style.fallback.p3_title'), t('style.fallback.p3_desc')],
                    ['fa-shield-halved',          t('style.fallback.p4_title'), t('style.fallback.p4_desc')],
                    ['fa-handshake',              t('style.fallback.p5_title'), t('style.fallback.p5_desc')],
                ];
            }
            foreach ($principles as $i => $p): ?>
            <div class="col-xl-4 col-md-6" style="margin-bottom:24px;">
                <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:12px;padding:28px;height:100%;display:flex;flex-direction:column;gap:14px;" class="wow fadeInUp" <?= $i>0?'data-wow-delay="0.'.$i.'s"':'' ?>>
                    <div style="width:48px;height:48px;border-radius:50%;background:rgba(26,188,156,.12);color:var(--accent-color,#1ABC9C);display:inline-flex;align-items:center;justify-content:center;font-size:18px;"><i class="fa-solid <?= e($p[0]) ?>"></i></div>
                    <h3 style="font-size:17px;color:var(--primary-color,#1B3A5C);margin-bottom:6px;"><?= e($p[1]) ?></h3>
                    <p style="font-size:13.5px;line-height:1.6;color:var(--mori-text-soft,#5A6B7B);margin:0;"><?= e($p[2]) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="our-services" style="padding:70px 0;">
    <div class="container">
        <div style="background:linear-gradient(135deg,var(--primary-color,#1B3A5C),var(--accent-secondary-color,#122842));color:#fff;border-radius:14px;padding:50px;text-align:center;">
            <h2 style="color:#fff;font-size:clamp(22px,2.6vw,30px);margin-bottom:14px;"><?= e(t('hero.cta_funds')) ?></h2>
            <p style="color:rgba(255,255,255,.8);max-width:620px;margin:0 auto 24px;font-size:15px;line-height:1.6;"><?= e(t('style.cta_text')) ?></p>
            <div style="display:inline-flex;gap:10px;flex-wrap:wrap;">
                <a href="<?= asset('fund-eastern-european.php') ?>" class="btn-default"><?= e(t('style.fund_card_ee')) ?></a>
                <a href="<?= asset('fund-ottoman.php') ?>" class="btn-default"><?= e(t('style.fund_card_otto')) ?></a>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
