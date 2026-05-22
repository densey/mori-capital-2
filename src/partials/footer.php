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
                                <p>Phone</p>
                                <h3><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></h3>
                            </div>
                            <div class="footer-contact-item">
                                <p>Email</p>
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
                            <li><a href="<?= asset('insights.php') ?>"><?= e(t('nav.insights')) ?></a></li>
                            <li><a href="<?= asset('contact.php') ?>"><?= e(t('nav.contact')) ?></a></li>
                        </ul>
                    </div>

                    <div class="footer-links">
                        <h2><?= e(t('footer.funds_docs')) ?></h2>
                        <ul>
                            <li><a href="<?= asset('fund-eastern-european.php') ?>">Mori Eastern European Fund</a></li>
                            <li><a href="<?= asset('fund-ottoman.php') ?>">Mori Ottoman Fund</a></li>
                            <li><a href="<?= asset('fund-performance.php') ?>">Performance</a></li>
                            <li><a href="<?= asset('documents.php?type=factsheet') ?>">Factsheets</a></li>
                            <li><a href="<?= asset('documents.php?type=kiid') ?>">KIIDs &amp; PRIIPs KIDs</a></li>
                            <li><a href="<?= asset('documents.php?type=annual') ?>">Annual &amp; Semi-Annual Reports</a></li>
                            <li><a href="<?= asset('documents.php') ?>">Document Hub</a></li>
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
                                <button type="submit" class="newsletter-btn" aria-label="Subscribe"><i class="fa-regular fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="footer-cta-box">
                    <div class="footer-logo">
                        <img src="<?= asset('assets/images/mori-capital-logo.fw.png') ?>" alt="Mori Capital Management">
                    </div>

                    <a href="<?= asset('legal.php') ?>" class="footer-regulator">
                        <i class="fa-solid fa-shield-halved"></i>
                        <?= e(t('footer.regulator')) ?>
                    </a>

                    <div class="footer-social-links">
                        <h3><?= e(t('footer.follow')) ?></h3>
                        <ul>
                            <li><a href="<?= e($linkedin) ?>" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                            <li><a href="mailto:<?= e($email) ?>" aria-label="Email"><i class="fa-regular fa-envelope"></i></a></li>
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
                            <li><a href="<?= asset('legal.php') ?>">Legal &amp; Disclaimer</a></li>
                            <li><a href="<?= asset('privacy.php') ?>">Privacy Policy</a></li>
                            <li><a href="<?= asset('cookies.php') ?>">Cookie Policy</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
