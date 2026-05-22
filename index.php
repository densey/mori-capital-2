<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\setting;
use function Mori\t;

// Pull live data from DB
$db = Database::instance();
try {
    $funds = $db->fetchAll(
        'SELECT * FROM funds WHERE status = "active" ORDER BY display_order ASC'
    );
    $team = $db->fetchAll(
        'SELECT * FROM team_members WHERE status = "active" ORDER BY display_order ASC'
    );
    $insights = $db->fetchAll(
        'SELECT * FROM insights WHERE status = "published" AND locale = :loc
           ORDER BY publish_date DESC LIMIT 3',
        ['loc' => I18n::locale()]
    );
} catch (\Throwable $ex) {
    // First-install — DB may not be ready. Fall back to empty arrays.
    $funds = $team = $insights = [];
}

$page = [
    'title'       => setting('seo_default_title', 'Mori Capital Management — Specialists in EEMEA Markets'),
    'description' => setting('seo_default_desc'),
    'body_class'  => 'home',
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
?>

    <!-- Hero -->
    <div class="hero dark-section" style="background:#0E1F36 url('<?= asset('assets/images/hero/hero-istanbul.jpg') ?>') center/cover no-repeat;">
        <div class="hero-bg-overlay" style="position:absolute;inset:0;background:linear-gradient(125deg, rgba(8,18,33,.78) 0%, rgba(18,40,66,.62) 50%, rgba(27,58,92,.45) 100%);pointer-events:none;z-index:1;"></div>

        <div class="container">
            <div class="row section-row align-items-center">
                <div class="col-xl-7">
                    <div class="section-title">
                        <span class="section-sub-title wow fadeInUp"><?= e(t('hero.eyebrow')) ?></span>
                        <h1 class="text-anime-style-3" data-cursor="-opaque"><?= e(t('hero.title')) ?></h1>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="section-content-btn">
                        <div class="section-title-content wow fadeInUp" data-wow-delay="0.2s">
                            <p><?= e(t('hero.lead')) ?></p>
                        </div>
                        <div class="section-btn wow fadeInUp" data-wow-delay="0.4s">
                            <a class="btn-default btn-highlighted" href="#funds"><?= e(t('hero.cta_funds')) ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- About Mori -->
    <div class="about-us">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-6">
                    <div class="about-us-image-box wow fadeInUp">
                        <div class="about-us-image-box-1">
                            <div class="about-us-image">
                                <figure class="image-anime">
                                    <img src="<?= asset('assets/images/service/h6-service-1.webp') ?>" alt="Eastern European markets — Mori Capital coverage">
                                </figure>
                            </div>
                        </div>
                        <div class="about-us-image-box-2">
                            <div class="about-us-image">
                                <figure class="image-anime">
                                    <img src="<?= asset('assets/images/service/h6-service-2.webp') ?>" alt="Türkiye and MENA markets — Mori Capital coverage">
                                </figure>
                            </div>
                            <div class="contact-us-circle">
                                <a href="<?= asset('contact.php') ?>">
                                    <img src="<?= asset('images/contact-us-circle.svg') ?>" alt="">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="about-us-content" style="padding-left:20px;">
                        <div class="section-title">
                            <span class="section-sub-title wow fadeInUp">About Mori Capital</span>
                            <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e(t('section.about.title')) ?></h2>
                            <p class="wow fadeInUp" data-wow-delay="0.2s">Founded in 1998 and headquartered in Malta, Mori Capital Management is a dedicated investor in Emerging European, Middle Eastern and African equity markets. We combine bottom-up stock picking with rigorous in-house research and active dialogue with company management.</p>
                        </div>

                        <div class="about-highlighted-box wow fadeInUp" data-wow-delay="0.4s">
                            <h3>&ldquo; In the EEMEA region, knowledge isn't found in screens &mdash; it's earned by walking the extra mile. &rdquo;</h3>
                        </div>

                        <div class="about-us-body">
                            <div class="about-body-content-box">
                                <div class="about-counter-item-list">
                                    <div class="about-counter-item">
                                        <h2><span class="counter">25</span>+</h2>
                                        <p>Years of EEMEA expertise</p>
                                    </div>
                                    <div class="about-counter-item">
                                        <h2><span class="counter">200</span>+</h2>
                                        <p>Securities under coverage</p>
                                    </div>
                                </div>
                                <div class="about-us-btn wow fadeInUp" data-wow-delay="0.2s">
                                    <a class="btn-default" href="<?= asset('about.php') ?>">More about Mori</a>
                                </div>
                            </div>
                            <div class="about-us-body-image wow fadeInUp" data-wow-delay="0.2s">
                                <figure class="image-anime">
                                    <img src="<?= asset('assets/images/about/about-hd.jpg') ?>" alt="Mori Capital — EEMEA research-led approach">
                                </figure>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Our Funds -->
    <div class="our-services" id="funds">
        <div class="container">
            <div class="row section-row align-items-end">
                <div class="col-xl-6">
                    <div class="section-title">
                        <span class="section-sub-title wow fadeInUp"><?= e(t('nav.funds')) ?></span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e(t('section.funds.title')) ?></h2>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="section-content-btn">
                        <div class="section-title-content wow fadeInUp" data-wow-delay="0.2s">
                            <p>Our flagship vehicles offer differentiated access to distinct EEMEA market opportunities &mdash; both managed with the same disciplined, research-led approach.</p>
                        </div>
                        <div class="section-btn wow fadeInUp" data-wow-delay="0.4s">
                            <a class="btn-default" href="<?= asset('documents.php') ?>">Fund Documents</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <?php $idx = 0; foreach ($funds as $f): $idx++; ?>
                <div class="col-xl-6 col-lg-6">
                    <div class="service-item wow fadeInUp" <?= $idx>1?'data-wow-delay="0.2s"':'' ?>>
                        <div class="service-item-image">
                            <a href="<?= asset('fund-' . $f['slug'] . '.php') ?>" data-cursor-text="View">
                                <figure class="image-anime">
                                    <img src="<?= asset(e($f['cover_image_path'] ?? 'assets/images/service/h6-service-' . $idx . '.webp')) ?>" alt="<?= e(I18n::fieldFor($f, 'name')) ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="service-item-body">
                            <div class="service-item-content">
                                <h2><a href="<?= asset(($f['slug']==='mori-eastern-european-fund'?'fund-eastern-european.php':'fund-ottoman.php')) ?>"><?= e(I18n::fieldFor($f, 'name')) ?></a></h2>
                                <p><?= e(I18n::fieldFor($f, 'description')) ?></p>
                            </div>
                            <div class="service-item-btn">
                                <a href="<?= asset(($f['slug']==='mori-eastern-european-fund'?'fund-eastern-european.php':'fund-ottoman.php')) ?>" class="readmore-btn"><?= e(t('btn.fund_details')) ?></a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="col-lg-12">
                    <div class="section-footer-text section-satisfy-img wow fadeInUp" data-wow-delay="0.2s">
                        <div class="satisfy-client-images">
                            <?php foreach (array_slice($team, 0, 2) as $tm): ?>
                            <div class="satisfy-client-image">
                                <figure class="image-anime">
                                    <img src="<?= asset(e($tm['photo_path'])) ?>" alt="<?= e($tm['name']) ?>">
                                </figure>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p>Managed by portfolio managers with 20+ years of EEMEA experience. &mdash; <a href="<?= asset('documents.php') ?>">View Fund Documents</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- The Mori Style -->
    <div class="why-choose-us">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-6">
                    <div class="why-choose-us-content">
                        <div class="section-title">
                            <span class="section-sub-title wow fadeInUp">The Mori Style</span>
                            <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e(t('section.style.title')) ?></h2>
                            <p class="wow fadeInUp" data-wow-delay="0.2s">Our investment philosophy is built on bottom-up stock picking with a macro overlay, in-house proprietary research, and active dialogue with company management.</p>
                        </div>

                        <div class="why-choose-list wow fadeInUp" data-wow-delay="0.4s">
                            <ul>
                                <li>Bottom-up stock picking with a top-down macro overlay across EEMEA markets</li>
                                <li>Active dialogue with company management and on-the-ground research visits</li>
                            </ul>
                        </div>

                        <div class="why-choose-items-list wow fadeInUp" data-wow-delay="0.6s">
                            <div class="why-choose-item">
                                <div class="icon-box"><img src="<?= asset('images/icon-why-choose-body-1.svg') ?>" alt=""></div>
                                <div class="why-choose-item-content"><h3>In-house proprietary research</h3></div>
                            </div>
                            <div class="why-choose-item">
                                <div class="icon-box"><img src="<?= asset('images/icon-why-choose-body-2.svg') ?>" alt=""></div>
                                <div class="why-choose-item-content"><h3>Walking the extra mile &mdash; 200+ securities</h3></div>
                            </div>
                            <div class="why-choose-item">
                                <div class="icon-box"><img src="<?= asset('images/icon-why-choose-body-3.svg') ?>" alt=""></div>
                                <div class="why-choose-item-content"><h3>Disciplined risk management</h3></div>
                            </div>
                        </div>

                        <div class="why-choose-btn wow fadeInUp" data-wow-delay="0.8s">
                            <a href="<?= asset('investment-style.php') ?>" class="btn-default">Explore the Mori Style</a>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="why-choose-us-image-box wow fadeInUp" data-wow-delay="0.2s">
                        <div class="why-choose-us-image">
                            <figure class="image-anime">
                                <img src="<?= asset('assets/images/hero/hero-corporate-2.jpg') ?>" alt="Mori Capital research-led investment style">
                            </figure>
                        </div>
                        <?php $pm = $team[0] ?? null; if ($pm): ?>
                        <div class="why-choose-cta-box">
                            <div class="why-choose-cta-box-content">
                                <h3>&ldquo;We don't just analyse companies &mdash; we visit them, walk their factories, and meet their stakeholders.&rdquo;</h3>
                            </div>
                            <div class="why-choose-author-box">
                                <div class="why-choose-author-image">
                                    <figure class="image-anime">
                                        <img src="<?= asset(e($pm['photo_path'])) ?>" alt="<?= e($pm['name']) ?>">
                                    </figure>
                                </div>
                                <div class="why-choose-author-content">
                                    <h3><?= e($pm['name']) ?></h3>
                                    <p><?= e($pm['title_en']) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cinematic 3D Showcase -->
    <div class="mori-cinematic" id="moriCinematic">
        <div class="mori-cinematic__bg" aria-hidden="true">
            <div class="mori-cinematic__grid"></div>
            <div class="mori-cinematic__glow mori-cinematic__glow--a"></div>
            <div class="mori-cinematic__glow mori-cinematic__glow--b"></div>
        </div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-5 col-lg-6">
                    <div class="mori-cinematic__copy">
                        <span class="section-sub-title wow fadeInUp">EEMEA in motion</span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque">Disciplined investing,<br>powered by data.</h2>
                        <p class="wow fadeInUp" data-wow-delay="0.2s">Real-time portfolio analytics, on-the-ground research and active risk management &mdash; all converging into a single conviction-led process across Emerging European, Middle Eastern and African markets.</p>
                        <div class="mori-cinematic__cta wow fadeInUp" data-wow-delay="0.4s">
                            <a class="btn-default btn-highlighted" href="<?= asset('investment-style.php') ?>">Discover the Mori Style</a>
                        </div>
                        <div class="mori-cinematic__stats">
                            <div><span class="num">15+</span><span class="lbl">EEMEA Markets</span></div>
                            <div><span class="num">200+</span><span class="lbl">Securities Tracked</span></div>
                            <div><span class="num">25+</span><span class="lbl">Years Experience</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7 col-lg-6">
                    <div class="mori-cinematic__stage">
                        <div class="mori-cinematic__card" id="moriTiltCard">
                            <div class="tilt-layer tilt-back">
                                <svg viewBox="0 0 600 360" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <defs>
                                        <pattern id="cineDots" x="0" y="0" width="14" height="14" patternUnits="userSpaceOnUse">
                                            <circle cx="2" cy="2" r="1" fill="#5DADE2" opacity="0.18"/>
                                        </pattern>
                                    </defs>
                                    <rect width="600" height="360" fill="url(#cineDots)"/>
                                    <g fill="#1ABC9C" opacity="0.08">
                                        <path d="M120,60 Q200,50 280,70 L340,90 Q360,130 320,150 L240,160 Q160,150 130,120 Z"/>
                                        <path d="M340,140 Q400,130 460,145 L490,170 Q470,200 420,205 L360,200 Q330,180 330,160 Z"/>
                                        <path d="M180,200 Q260,195 340,210 L370,240 Q340,275 280,280 L210,275 Q170,250 170,225 Z"/>
                                    </g>
                                </svg>
                            </div>
                            <div class="tilt-layer tilt-mid">
                                <div class="tilt-chart">
                                    <div class="tilt-chart__head">
                                        <div>
                                            <div class="lbl">Mori Ottoman Fund · NAV</div>
                                            <div class="val">€ 142.86 <span class="up">▲ 18.4% YTD</span></div>
                                        </div>
                                        <div class="tilt-chart__pills">
                                            <span class="pill active">1Y</span>
                                            <span class="pill">3Y</span>
                                            <span class="pill">5Y</span>
                                            <span class="pill">10Y</span>
                                        </div>
                                    </div>
                                    <svg class="tilt-chart__svg" viewBox="0 0 560 180" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="cineFill" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#1ABC9C" stop-opacity="0.45"/>
                                                <stop offset="100%" stop-color="#1ABC9C" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <g stroke="#5DADE2" stroke-width="0.4" opacity="0.18">
                                            <line x1="0" y1="40" x2="560" y2="40"/>
                                            <line x1="0" y1="90" x2="560" y2="90"/>
                                            <line x1="0" y1="140" x2="560" y2="140"/>
                                        </g>
                                        <path d="M10,140 L60,128 L110,135 L160,118 L210,120 L260,98 L310,102 L360,82 L410,86 L460,68 L510,60 L550,46 L550,180 L10,180 Z" fill="url(#cineFill)"/>
                                        <polyline points="10,140 60,128 110,135 160,118 210,120 260,98 310,102 360,82 410,86 460,68 510,60 550,46" fill="none" stroke="#1ABC9C" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="260" cy="98" r="4" fill="#1ABC9C"/>
                                        <circle cx="260" cy="98" r="9" fill="none" stroke="#1ABC9C" stroke-width="1.2" opacity="0.55"/>
                                        <circle cx="510" cy="60" r="3" fill="#5DADE2"/>
                                        <circle cx="160" cy="118" r="3" fill="#5DADE2"/>
                                    </svg>
                                    <div class="tilt-chart__foot">
                                        <span>Apr '25</span><span>Jul</span><span>Oct</span><span>Jan '26</span><span>Apr</span>
                                    </div>
                                </div>
                            </div>
                            <div class="tilt-layer tilt-chip tilt-chip--ccy">
                                <i class="fa-solid fa-coins"></i>
                                <div><div class="chip-lbl">Multi-currency</div><div class="chip-val">EUR · USD · GBP · TRY</div></div>
                            </div>
                            <div class="tilt-layer tilt-chip tilt-chip--perf">
                                <i class="fa-solid fa-arrow-trend-up"></i>
                                <div><div class="chip-lbl">10Y Annualised</div><div class="chip-val">+11.7% <span style="color:#1ABC9C;font-size:11px;">vs benchmark</span></div></div>
                            </div>
                            <div class="tilt-layer tilt-chip tilt-chip--aum">
                                <i class="fa-solid fa-shield-halved"></i>
                                <div><div class="chip-lbl">UCITS · Daily liquidity</div><div class="chip-val">MFSA C66999</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($insights)): ?>
    <!-- Latest Insights -->
    <div class="mori-insights" style="padding:80px 0;background:#FFFFFF;">
        <div class="container">
            <div class="row section-row align-items-end">
                <div class="col-xl-6">
                    <div class="section-title">
                        <span class="section-sub-title wow fadeInUp">Mori Views</span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e(t('section.views.title')) ?></h2>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="section-content-btn">
                        <div class="section-title-content wow fadeInUp" data-wow-delay="0.2s">
                            <p>Quarterly outlooks, fund factsheets and shareholder communications from our portfolio managers.</p>
                        </div>
                        <div class="section-btn wow fadeInUp" data-wow-delay="0.4s">
                            <a class="btn-default" href="<?= asset('insights.php') ?>">All Mori Views</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row" style="margin-top:24px;">
                <?php $ix = 0; foreach ($insights as $ins): $ix++; ?>
                <div class="col-xl-4 col-md-6">
                    <a href="<?= asset('insights/' . e($ins['slug']) . '.php') ?>" class="insight-card wow fadeInUp" <?= $ix>1?'data-wow-delay="0.'.($ix-1).'s"':'' ?>>
                        <div class="insight-meta">
                            <span><?= e(ucwords(str_replace('_',' ',$ins['category']))) ?></span>
                            <span class="date"><?= e(\Mori\format_date($ins['publish_date'])) ?></span>
                        </div>
                        <h3><?= e($ins['title']) ?></h3>
                        <p><?= e($ins['excerpt']) ?></p>
                        <span class="insight-link">Read more <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Team -->
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
                        <div class="section-title-content wow fadeInUp" data-wow-delay="0.2s">
                            <p>Our funds are managed by an independent team with over 80 years of collective experience across Emerging European and Turkish capital markets, supported by a dedicated operations, compliance and risk function in Malta.</p>
                        </div>
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
                    <div class="team-item wow fadeInUp" <?= $tx>1?'data-wow-delay="0.'.($tx<10?$tx-1:0).'s"':'' ?>>
                        <div class="team-item-image">
                            <a href="<?= asset('team.php#' . e($member['slug'])) ?>" data-cursor-text="View">
                                <figure>
                                    <img src="<?= e($photoSrc) ?>" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($member['name']) ?>&background=1B3A5C&color=fff&size=600&bold=true&font-size=0.36'" alt="<?= e($member['name']) ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="team-item-body">
                            <div class="team-item-content">
                                <h2><a href="<?= asset('team.php#' . e($member['slug'])) ?>"><?= e($member['name']) ?></a></h2>
                            </div>
                            <div class="team-social-list">
                                <p><?= e(I18n::fieldFor($member, 'title')) ?></p>
                                <ul>
                                    <?php if (!empty($member['linkedin_url'])): ?>
                                    <li><a href="<?= e($member['linkedin_url']) ?>" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
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

<?php
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';

// Tilt JS for the cinematic card
?>
<script>
(function () {
    var card = document.getElementById('moriTiltCard');
    var section = document.getElementById('moriCinematic');
    if (!card || !section) return;
    var maxTilt = 9, rafId = null, targetX = 0, targetY = 0, currentX = 0, currentY = 0, lerp = 0.12;
    function tick() {
        currentX += (targetX - currentX) * lerp;
        currentY += (targetY - currentY) * lerp;
        card.style.transform = 'rotateX(' + (-currentY * maxTilt).toFixed(2) + 'deg) rotateY(' + (currentX * maxTilt).toFixed(2) + 'deg)';
        if (Math.abs(targetX - currentX) > 0.001 || Math.abs(targetY - currentY) > 0.001) rafId = requestAnimationFrame(tick);
        else rafId = null;
    }
    function schedule() { if (rafId == null) rafId = requestAnimationFrame(tick); }
    section.addEventListener('mousemove', function (e) {
        var rect = card.getBoundingClientRect();
        var cx = rect.left + rect.width / 2;
        var cy = rect.top + rect.height / 2;
        targetX = Math.max(-1, Math.min(1, (e.clientX - cx) / (rect.width * 0.6)));
        targetY = Math.max(-1, Math.min(1, (e.clientY - cy) / (rect.height * 0.6)));
        schedule();
    });
    section.addEventListener('mouseleave', function () { targetX = 0; targetY = 0; schedule(); });
    var idleT = 0;
    setInterval(function () {
        if (rafId != null) return;
        idleT += 0.015;
        targetX = Math.sin(idleT) * 0.18;
        targetY = Math.cos(idleT * 0.8) * 0.12;
        schedule();
    }, 50);
})();
</script>
