<?php
/**
 * Dynamic XML sitemap for both EN + DE locales.
 * Lists: static pages, fund detail pages, published insights, published custom pages.
 *
 * Map at /sitemap.xml via .htaccess rewrite.
 */
require __DIR__ . '/src/bootstrap.php';

use Mori\Database;
use function Mori\url;

header('Content-Type: application/xml; charset=UTF-8');

$urls = [];

// Static public pages — extension-less SEO-friendly slugs (.htaccess rewrites)
$static = [
    '/'                       => ['weekly',  '1.0'],
    '/about'                  => ['monthly', '0.8'],
    '/investment-style'       => ['monthly', '0.8'],
    '/fund-eastern-european'  => ['weekly',  '0.9'],
    '/fund-ottoman'           => ['weekly',  '0.9'],
    '/fund-performance'       => ['daily',   '0.8'],
    '/documents'              => ['weekly',  '0.8'],
    '/team'                   => ['monthly', '0.6'],
    '/insights'               => ['weekly',  '0.7'],
    '/contact'                => ['monthly', '0.5'],
    '/legal'                  => ['yearly',  '0.3'],
    '/privacy'                => ['yearly',  '0.3'],
    '/cookies'                => ['yearly',  '0.3'],
];
foreach ($static as $path => [$freq, $prio]) {
    foreach (['en', 'de'] as $loc) {
        $slug = ltrim($path, '/');
        $href = url('/' . $loc . ($slug ? '/' . $slug : '/'));
        $urls[] = ['loc' => $href, 'lastmod' => date('Y-m-d'), 'changefreq' => $freq, 'priority' => $prio];
    }
}

// DB-driven published insights
try {
    $db = Database::instance();
    foreach ($db->fetchAll('SELECT slug, locale, GREATEST(updated_at, publish_date) AS lm FROM insights WHERE status="published"') as $ins) {
        $urls[] = [
            'loc'        => url('/' . $ins['locale'] . '/insight?slug=' . urlencode($ins['slug'])),
            'lastmod'    => date('Y-m-d', strtotime((string)$ins['lm'])),
            'changefreq' => 'monthly',
            'priority'   => '0.6',
        ];
    }
} catch (\Throwable) {}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($u['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($u['lastmod']) . "</lastmod>\n";
    echo "    <changefreq>" . htmlspecialchars($u['changefreq']) . "</changefreq>\n";
    echo "    <priority>" . htmlspecialchars($u['priority']) . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
