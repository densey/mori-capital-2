<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\t;

$slug = 'mori-eastern-european-fund';
$fund = null; $shareClasses = []; $documents = [];

try {
    $db = Database::instance();
    $fund = $db->fetchOne('SELECT * FROM funds WHERE slug = :s LIMIT 1', ['s' => $slug]);
    $shareClasses = $fund ? $db->fetchAll('SELECT * FROM share_classes WHERE fund_id = :id ORDER BY display_order', ['id' => $fund['id']]) : [];
    $documents = $fund ? $db->fetchAll('SELECT * FROM documents WHERE fund_id = :id ORDER BY document_date DESC LIMIT 12', ['id' => $fund['id']]) : [];
} catch (\Throwable) {}

$page = [
    'title'       => 'Mori Eastern European Fund — Mori Capital',
    'description' => 'Long-only equity fund investing across Central and Eastern Europe since 1998.',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.funds'), 'url' => asset('/#funds')],
        ['label' => 'Mori Eastern European Fund'],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
include __DIR__ . '/src/partials/fund-detail.php';
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
