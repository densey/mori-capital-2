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
                <a class="navbar-brand" href="<?= asset('/') ?>" aria-label="<?= e(t('header.logo_aria')) ?>">
                    <img class="logo-light" src="/<?= e(ltrim($logoLight, '/')) ?>" alt="<?= e(t('header.logo_aria')) ?>">
                    <img class="logo-dark"  src="/<?= e(ltrim($logoDark, '/')) ?>" alt="<?= e(t('header.logo_aria')) ?>">
                </a>

                <div class="collapse navbar-collapse main-menu">
                    <div class="nav-menu-wrapper">
                        <ul class="navbar-nav mr-auto" id="menu">
                            <li class="nav-item <?= is_active_nav('/about.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('about.php') ?>"><?= e(t('nav.about')) ?></a>
                            </li>
                            <li class="nav-item <?= is_active_nav('/investment-style.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('investment-style.php') ?>"><?= e(t('nav.investment_style')) ?></a>
                            </li>
                            <li class="nav-item submenu">
                                <a class="nav-link" href="#funds"><?= e(t('nav.funds')) ?></a>
                                <ul>
                                    <?php
                                    try {
                                        $navFunds = \Mori\Database::instance()->fetchAll('SELECT slug, name_en, name_de FROM funds WHERE status = "active" ORDER BY display_order');
                                    } catch (\Throwable $e) { $navFunds = []; }
                                    foreach ($navFunds as $nf):
                                        $fundName = \Mori\I18n::fieldFor($nf, 'name');
                                        $fundUrl = ($nf['slug'] === 'mori-eastern-european-fund') ? 'fund-eastern-european.php' : 'fund-ottoman.php';
                                    ?>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset($fundUrl) ?>"><?= e($fundName) ?></a></li>
                                    <?php endforeach; ?>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('fund-performance.php') ?>"><?= e(t('nav.performance')) ?></a></li>
                                </ul>
                            </li>
                            <li class="nav-item submenu <?= is_active_nav('/documents.php')||is_active_nav('/company-policies.php')||is_active_nav('/other-documents.php')||is_active_nav('/updates-during-suspension.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('documents.php') ?>"><?= e(t('nav.documents')) ?></a>
                                <ul>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('documents.php') ?>"><?= e(t('doc.share_class_docs')) ?></a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('company-policies.php') ?>"><?= e(t('doc.company_policies')) ?></a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('other-documents.php') ?>"><?= e(t('doc.other_documents')) ?></a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= asset('updates-during-suspension.php') ?>"><?= e(t('doc.suspension_updates')) ?></a></li>
                                </ul>
                            </li>
                            <li class="nav-item <?= is_active_nav('/announcements.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('announcements.php') ?>"><?= e(t('nav.announcements')) ?></a>
                            </li>
                            <li class="nav-item <?= is_active_nav('/media.php')?'active':'' ?>">
                                <a class="nav-link" href="<?= asset('media.php') ?>"><?= e(t('nav.media')) ?></a>
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
                <button class="mori-hamburger" id="moriMenuToggle" aria-label="<?= e(t('header.menu_aria')) ?>">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </nav>
        <div class="responsive-menu"></div>
    </div>
</header>
