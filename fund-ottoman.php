<?php
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\t;

$db = Database::instance();
$slug = 'mori-ottoman-fund';

try {
    $fund = $db->fetchOne('SELECT * FROM funds WHERE slug = :s LIMIT 1', ['s' => $slug]);
    $shareClasses = $fund ? $db->fetchAll('SELECT * FROM share_classes WHERE fund_id = :id ORDER BY display_order', ['id' => $fund['id']]) : [];
    $documents = $fund ? $db->fetchAll('SELECT * FROM documents WHERE fund_id = :id ORDER BY document_date DESC LIMIT 12', ['id' => $fund['id']]) : [];
} catch (\Throwable) {
    $fund = null; $shareClasses = []; $documents = [];
}

$page = [
    'title'       => 'Mori Ottoman Fund — Mori Capital',
    'description' => 'Türkiye and MENA focused equity fund. Award-winning long-term track record. Launched January 2006.',
    'breadcrumb'  => [
        ['label' => t('nav.home'), 'url' => asset('/')],
        ['label' => t('nav.funds'), 'url' => asset('/#funds')],
        ['label' => 'Mori Ottoman Fund'],
    ],
];

include __DIR__ . '/src/partials/head.php';
include __DIR__ . '/src/partials/topbar.php';
include __DIR__ . '/src/partials/header.php';
include __DIR__ . '/src/partials/page-header.php';
include __DIR__ . '/src/partials/fund-detail.php';
include __DIR__ . '/src/partials/footer.php';
include __DIR__ . '/src/partials/scripts.php';
