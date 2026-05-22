<?php
use Mori\I18n;
use function Mori\e;
use function Mori\setting;
use function Mori\t;

$phone = setting('contact_phone', '+356 2033 0110');
$email = setting('contact_email', 'info@mori-capital.com');
$linkedin = setting('linkedin_url', '#');

// Build language-switcher links: keep current path, toggle ?lang=
$path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$enUrl = $path . '?lang=en';
$deUrl = $path . '?lang=de';
$cur = I18n::locale();
?>
<!-- Topbar Section -->
<div class="topbar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7 col-md-7">
                <div class="topbar-contact-info">
                    <p><i class="fa-solid fa-phone-volume"></i> <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a> &nbsp;&middot;&nbsp; <i class="fa-regular fa-envelope"></i> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
                </div>
            </div>

            <div class="col-lg-5 col-md-5">
                <div class="topbar-link-box">
                    <div class="topbar-contact-info-list">
                        <ul>
                            <li><a href="<?= \Mori\asset('contact.php') ?>"><?= e(t('topbar.contact')) ?></a></li>
                        </ul>
                    </div>

                    <div class="topbar-social-links">
                        <ul>
                            <li><a href="<?= e($linkedin) ?>" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                            <li><a href="mailto:<?= e($email) ?>" aria-label="Email"><i class="fa-regular fa-envelope"></i></a></li>
                        </ul>
                    </div>

                    <div class="lang-switcher" role="group" aria-label="Language switcher">
                        <a href="<?= e($enUrl) ?>" class="<?= $cur==='en'?'active':'' ?>" <?= $cur==='en'?'aria-current="page"':'' ?>>EN</a>
                        <a href="<?= e($deUrl) ?>" class="<?= $cur==='de'?'active':'' ?>" <?= $cur==='de'?'aria-current="page"':'' ?>>DE</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
