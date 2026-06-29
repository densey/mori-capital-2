<?php
use Mori\Csrf;
use function Mori\e;
use function Mori\asset;
use function Mori\setting;
use function Mori\t;

$phone   = setting('contact_phone', '+356 2033 0110');
$email   = setting('contact_email', 'info@mori-capital.com');
$address = setting('contact_address', "Mori Capital Management Ltd.\nRegent House, Office 35\nBisazza Street, Sliema SLM 1640, Malta");
$linkedin = setting('linkedin_url', '#');
$year = date('Y');
?>
<!-- Main Footer -->
<footer class="main-footer dark-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="footer-links-box">
                    <div class="footer-contact-list-box footer-links">
                        <div class="footer-contact-box-title">
                            <h2><?= e(t('footer.contact')) ?></h2>
                        </div>
                        <div class="footer-contact-items-list">
                            <div class="footer-contact-item">
                                <p><?= nl2br(e($address)) ?></p>
                            </div>
                            <div class="footer-contact-item">
                                <p><?= e(t('footer.phone_label')) ?></p>
                                <h3><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></h3>
                            </div>
                            <div class="footer-contact-item">
                                <p><?= e(t('footer.email_label')) ?></p>
                                <h3><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></h3>
                            </div>
                        </div>
                    </div>

                    <div class="footer-links">
                        <h2><?= e(t('footer.mori_capital')) ?></h2>
                        <ul>
                            <li><a href="<?= asset('/') ?>"><?= e(t('nav.home')) ?></a></li>
                            <li><a href="<?= asset('about.php') ?>"><?= e(t('nav.about')) ?></a></li>
                            <li><a href="<?= asset('investment-style.php') ?>"><?= e(t('nav.investment_style')) ?></a></li>
                            <li><a href="<?= asset('team.php') ?>"><?= e(t('nav.team')) ?></a></li>
                            <li><a href="<?= asset('media.php') ?>"><?= e(t('nav.media')) ?></a></li>
                            <li><a href="<?= asset('contact.php') ?>"><?= e(t('nav.contact')) ?></a></li>
                        </ul>
                    </div>

                    <div class="footer-links">
                        <h2><?= e(t('footer.funds_docs')) ?></h2>
                        <ul>
                            <?php
                            try {
                                $ftFunds = \Mori\Database::instance()->fetchAll('SELECT slug, name_en, name_de FROM funds WHERE status = "active" ORDER BY display_order');
                            } catch (\Throwable $e) { $ftFunds = []; }
                            foreach ($ftFunds as $ff):
                                $ffName = \Mori\I18n::fieldFor($ff, 'name');
                                $ffUrl = ($ff['slug'] === 'mori-eastern-european-fund') ? 'fund-eastern-european.php' : 'fund-ottoman.php';
                            ?>
                            <li><a href="<?= asset($ffUrl) ?>"><?= e($ffName) ?></a></li>
                            <?php endforeach; ?>
                            <li><a href="<?= asset('fund-performance.php') ?>"><?= e(t('nav.performance')) ?></a></li>
                            <li><a href="<?= asset('documents.php') ?>"><?= e(t('btn.document_hub')) ?></a></li>
                        </ul>
                    </div>

                    <div class="footer-newsletter-form footer-links">
                        <div class="footer-newsletter-title-box">
                            <h2><?= e(t('footer.stay_informed')) ?></h2>
                            <p><?= e(t('footer.newsletter_p')) ?></p>
                        </div>
                        <form id="newslettersForm" action="<?= asset('api/newsletter.php') ?>" method="POST">
                            <?= Csrf::field() ?>
                            <div class="form-group">
                                <input type="email" name="mail" class="form-control" id="mail" placeholder="<?= e(t('contact.form.email')) ?>" required>
                                <button type="submit" class="newsletter-btn" aria-label="<?= e(t('btn.subscribe')) ?>"><i class="fa-regular fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="footer-cta-box">
                    <?php $fLogo = \Mori\setting('logo_light_path') ?: 'assets/images/mori-capital-logo.fw.png'; ?>
                    <div class="footer-logo">
                        <img src="/<?= \Mori\e(ltrim($fLogo, '/')) ?>" alt="<?= e(t('footer.logo_alt')) ?>">
                    </div>

                    <a href="<?= asset('legal.php') ?>" class="footer-regulator">
                        <i class="fa-solid fa-shield-halved"></i>
                        <?= e(t('footer.regulator')) ?>
                    </a>

                    <div class="footer-social-links">
                        <h3><?= e(t('footer.follow')) ?></h3>
                        <ul>
                            <li><a href="<?= e($linkedin) ?>" aria-label="<?= e(t('social.linkedin')) ?>"><i class="fa-brands fa-linkedin-in"></i></a></li>
                            <li><a href="mailto:<?= e($email) ?>" aria-label="<?= e(t('social.email')) ?>"><i class="fa-regular fa-envelope"></i></a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="footer-disclaimer">
                    <p><strong><?= e(t('disclaimer.title')) ?>.</strong> <?= e(t('disclaimer.body')) ?></p>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="footer-copyright">
                    <div class="footer-copyright-text">
                        <p><?= e(t('footer.copyright', ['year' => $year])) ?></p>
                    </div>
                    <div class="footer-privacy-policy">
                        <ul>
                            <li><a href="<?= asset('legal.php') ?>"><?= e(t('footer.legal_disclaimer')) ?></a></li>
                            <li><a href="<?= asset('privacy.php') ?>"><?= e(t('footer.privacy_policy')) ?></a></li>
                            <li><a href="<?= asset('cookies.php') ?>"><?= e(t('footer.cookie_policy')) ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Mobile bottom tab bar -->
<nav class="mori-tabbar" aria-label="<?= e(t('tabbar.nav_aria')) ?>">
    <div class="mori-tabbar__inner">
        <a href="<?= asset('/') ?>" class="mori-tabbar__item<?= (\Mori\current_path()==='/' || \Mori\current_path()==='/index.php')?' active':'' ?>" aria-label="<?= e(t('tabbar.home')) ?>">
            <i class="fa-solid fa-house"></i><span><?= e(t('tabbar.home')) ?></span>
        </a>
        <a href="<?= asset('fund-eastern-european.php') ?>" class="mori-tabbar__item<?= str_contains(\Mori\current_path(),'fund')?' active':'' ?>" aria-label="<?= e(t('tabbar.funds')) ?>">
            <i class="fa-solid fa-chart-pie"></i><span><?= e(t('tabbar.funds')) ?></span>
        </a>
        <a href="<?= asset('documents.php') ?>" class="mori-tabbar__item<?= str_contains(\Mori\current_path(),'documents')?' active':'' ?>" aria-label="<?= e(t('nav.documents')) ?>">
            <i class="fa-solid fa-folder-open"></i><span><?= e(t('tabbar.docs')) ?></span>
        </a>
        <a href="<?= asset('media.php') ?>" class="mori-tabbar__item<?= str_contains(\Mori\current_path(),'media')?' active':'' ?>" aria-label="<?= e(t('nav.media')) ?>">
            <i class="fa-solid fa-photo-film"></i><span><?= e(t('nav.media')) ?></span>
        </a>
        <a href="<?= asset('contact.php') ?>" class="mori-tabbar__item<?= str_contains(\Mori\current_path(),'contact')?' active':'' ?>" aria-label="<?= e(t('nav.contact')) ?>">
            <i class="fa-solid fa-envelope"></i><span><?= e(t('tabbar.contact')) ?></span>
        </a>
    </div>
</nav>
