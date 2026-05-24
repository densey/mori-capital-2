<?php
use function Mori\e;
use function Mori\asset;
use function Mori\is_active_nav;
use function Mori\t;
?>
<!-- Header -->
<header class="main-header active-sticky-header">
    <div class="header-sticky">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <?php
                    $logoLight = \Mori\setting('logo_light_path') ?: 'assets/images/mori-capital-logo.fw.png';
                    $logoDark  = \Mori\setting('logo_dark_path')  ?: 'assets/images/mori-capital-logo-dark.fw.png';
                ?>
                <a class="navbar-brand" href="<?= asset('/') ?>" aria-label="Mori Capital Management">
                    <img class="logo-light" src="/<?= e(ltrim($logoLight, '/')) ?>" alt="Mori Capital Management">
                    <img class="logo-dark"  src="/<?= e(ltrim($logoDark, '/')) ?>" alt="Mori Capital Management">
                </a>

                <div class="collapse navbar-collapse main-menu">
                    <div class="nav-menu-wrapper">
                        <ul class="navbar-nav mr-auto" id="menu">
                            <li class="nav-item <?= is_active_nav('/index.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('/') ?>"><?= e(t('nav.home')) ?></a>
                            </li>
                            <li class="nav-item <?= is_active_nav('/about.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('about.php') ?>"><?= e(t('nav.about')) ?></a>
                            </li>
                            <li class="nav-item <?= is_active_nav('/investment-style.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('investment-style.php') ?>"><?= e(t('nav.investment_style')) ?></a>
                            </li>
                            <li class="nav-item submenu">
                                <a class="nav-link" href="#funds"><?= e(t('nav.funds')) ?></a>
                                <ul>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('fund-eastern-european.php') ?>">Mori Eastern European Fund</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('fund-ottoman.php') ?>">Mori Ottoman Fund</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('fund-performance.php') ?>">Performance</a></li>
                                </ul>
                            </li>
                            <li class="nav-item <?= is_active_nav('/documents.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('documents.php') ?>"><?= e(t('nav.documents')) ?></a>
                            </li>
                            <li class="nav-item <?= is_active_nav('/contact.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('contact.php') ?>"><?= e(t('nav.contact')) ?></a>
                            </li>
                        </ul>
                    </div>

                    <div class="header-btn">
                        <a href="<?= asset('documents.php') ?>" class="btn-default btn-highlighted">
                            <i class="fa-regular fa-folder-open"></i> <?= e(t('btn.document_hub')) ?>
                        </a>
                    </div>
                </div>
                <!-- Mobile hamburger (visible <992px) -->
                <button class="mori-hamburger" id="moriMenuToggle" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </nav>
        <div class="responsive-menu"></div>
    </div>
</header>
