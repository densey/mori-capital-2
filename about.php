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
    $db = Database::instance();
    $pageData = $db->fetchOne(
        'SELECT * FROM pages WHERE slug = "about" AND locale = :loc AND status = "published"',
        ['loc' => I18n::locale()]
    ) ?? $db->fetchOne('SELECT * FROM pages WHERE slug = "about" AND locale = "en"');
    $team = $db->fetchAll('SELECT * FROM team_members WHERE status = "active" ORDER BY display_order LIMIT 6');
} catch (\Throwable) { $pageData = null; $team = []; }

$page = [
    'title'       => ($pageData['meta_title'] ?? null) ?: t('page.about.title'),
    'description' => ($pageData['meta_description'] ?? null) ?: t('page.about.desc'),
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.about')],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
?>

<!-- About content -->
<div class="about-us" style="padding-top:80px;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-xl-6">
                <div class="about-us-image-box wow fadeInUp">
                    <div class="about-us-image-box-1">
                        <div class="about-us-image">
                            <figure class="image-anime">
                                <img src="<?= asset('assets/images/about/about-hd.jpg') ?>" alt="<?= e(t('about.alt_research')) ?>">
                            </figure>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="about-us-content" style="padding-left:clamp(0px,2vw,20px);">
                    <div class="section-title">
                        <span class="section-sub-title wow fadeInUp"><?= e(t('nav.about')) ?></span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e($pageData['title'] ?? t('section.about.title')) ?></h2>
                    </div>
                    <div class="wow fadeInUp" data-wow-delay="0.2s">
                        <?= $pageData['body'] ?? t('about.body_fallback') ?>
                    </div>

                    <div class="about-highlighted-box wow fadeInUp" data-wow-delay="0.4s">
                        <h3>&ldquo; <?= e(\Mori\setting_i18n('hp_about_quote', "In the EEMEA region, knowledge isn't found in screens — it's earned by walking the extra mile.")) ?> &rdquo;</h3>
                    </div>

                    <div class="about-counter-item-list wow fadeInUp" data-wow-delay="0.6s">
                        <div class="about-counter-item">
                            <h2><span class="counter"><?= e(\Mori\setting('stat_years', '25')) ?></span>+</h2>
                            <p><?= e(t('stat.years_expertise')) ?></p>
                        </div>
                        <div class="about-counter-item">
                            <h2><span class="counter"><?= e(\Mori\setting('stat_securities', '200')) ?></span>+</h2>
                            <p><?= e(t('stat.securities_coverage')) ?></p>
                        </div>
                        <div class="about-counter-item">
                            <h2><span class="counter"><?= e(\Mori\setting('stat_team_experience', '80')) ?></span>+</h2>
                            <p><?= e(t('stat.team_experience')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Regulatory info -->
<div class="our-services" style="background:var(--mori-bg-soft,#F5F7FA);padding:70px 0;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-xl-6">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp"><?= e(t('about.regulatory_eyebrow')) ?></span>
                    <h2 style="font-size:clamp(24px,2.8vw,32px);"><?= e(setting('mfsa_authority', 'Malta Financial Services Authority')) ?></h2>
                </div>
                <p style="font-size:15px;line-height:1.7;color:var(--mori-text-soft,#5A6B7B);"><?= str_replace(':license', '<strong>' . e(setting('mfsa_license', 'C66999')) . '</strong>', t('about.regulatory_body')) ?></p>
                <div style="display:inline-flex;align-items:center;gap:10px;background:#fff;border:1px solid var(--mori-border,#E1E7EE);padding:14px 18px;border-radius:8px;font-size:13px;color:var(--primary-color,#1B3A5C);font-weight:600;margin-top:10px;">
                    <i class="fa-solid fa-shield-halved" style="color:var(--accent-color,#1ABC9C);"></i>
                    <?= e(t('about.regulator_badge')) ?> <?= e(setting('mfsa_license', 'C66999')) ?>
                </div>
            </div>
            <div class="col-xl-6">
                <div style="background:#fff;border:1px solid var(--mori-border,#E1E7EE);border-radius:12px;padding:30px;">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
                        <div style="width:46px;height:46px;border-radius:8px;background:rgba(26,188,156,.12);color:var(--accent-color,#1ABC9C);display:inline-flex;align-items:center;justify-content:center;"><i class="fa-solid fa-location-dot"></i></div>
                        <div>
                            <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.12em;font-weight:600;color:var(--mori-muted,#7A8B99);"><?= e(t('about.registered_office')) ?></div>
                            <div style="font-size:14px;color:var(--primary-color,#1B3A5C);font-weight:600;margin-top:2px;">Mori Capital Management Ltd.</div>
                        </div>
                    </div>
                    <p style="font-size:14px;color:var(--mori-text-soft,#5A6B7B);line-height:1.65;margin:0;">
                        <?= nl2br(e(setting('contact_address', "Regent House, Office 35\nBisazza Street, Sliema SLM 1640, Malta"))) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Team preview -->
<?php if (!empty($team)): ?>
<div class="page-team" style="padding:80px 0;">
    <div class="container">
        <div class="row section-row align-items-end">
            <div class="col-xl-6">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp"><?= e(t('nav.team')) ?></span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e(t('section.team.title')) ?></h2>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="section-content-btn">
                    <div class="section-btn wow fadeInUp" data-wow-delay="0.4s">
                        <a class="btn-default" href="<?= asset('team.php') ?>"><?= e(t('btn.meet_team')) ?></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="row" style="margin-top:24px;">
            <?php $tx = 0; foreach ($team as $member): $tx++;
                $photoSrc = $member['photo_path'] ?? '';
                if (!preg_match('/^https?:\/\//', $photoSrc)) $photoSrc = asset($photoSrc);
            ?>
            <div class="col-xl-4 col-md-6">
                <div class="team-item wow fadeInUp">
                    <div class="team-item-image">
                        <a href="<?= asset('team.php#' . e($member['slug'])) ?>"><figure>
                            <img src="<?= e($photoSrc) ?>" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($member['name']) ?>&background=1B3A5C&color=fff&size=600&bold=true&font-size=0.36'" alt="<?= e($member['name']) ?>">
                        </figure></a>
                    </div>
                    <div class="team-item-body">
                        <div class="team-item-content"><h2><a href="<?= asset('team.php#' . e($member['slug'])) ?>"><?= e($member['name']) ?></a></h2></div>
                        <div class="team-social-list">
                            <p><?= e(I18n::fieldFor($member, 'title')) ?></p>
                            <ul>
                                <?php if (!empty($member['linkedin_url'])): ?>
                                <li><a href="<?= e(safe_url($member['linkedin_url'] ?? null)) ?>" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                                <?php endif; ?>
                                <li><a href="mailto:<?= e($member['email'] ?: setting('contact_email')) ?>" aria-label="Email"><i class="fa-regular fa-envelope"></i></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
