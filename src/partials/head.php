<?php
/**
 * <head> + opening <body> + preloader.
 * Expects: $page = [
 *   'title', 'description', 'keywords', 'body_class',
 *   'og_image' (optional, full URL or relative path),
 *   'og_type'  (optional, default 'website')
 * ]
 */
use Mori\I18n;
use function Mori\e;
use function Mori\asset;
use function Mori\setting;

$page = $page ?? [];
$title = $page['title']       ?? (setting('seo_default_title') ?? 'Mori Capital Management');
$desc  = $page['description'] ?? (setting('seo_default_desc') ?? '');
$kw    = $page['keywords']    ?? 'Mori Capital, EEMEA, Emerging Europe, Ottoman Fund, asset management, Malta, MFSA';
$bodyClass = $page['body_class'] ?? '';
$ga = setting('google_analytics_id', '');

// Canonical & social URLs — strip ".php" so search engines index clean URLs
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'mori-capital.com';
$path   = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
// Canonical: drop .php so /about.php and /about share the same URL signal
$canonicalPath = preg_replace('/\.php$/', '', $path);
if ($canonicalPath === '' || $canonicalPath === '/index') $canonicalPath = '/';
$canonical = $scheme . '://' . $host . $canonicalPath;

$ogImageSetting = setting('og_default_image', 'assets/images/mori-capital-logo.fw.png');
$ogImage = $page['og_image'] ?? $ogImageSetting;
if (!preg_match('/^https?:\/\//', (string)$ogImage)) {
    $ogImage = $scheme . '://' . $host . '/' . ltrim((string)$ogImage, '/');
}
$ogType = $page['og_type'] ?? 'website';
$siteName = setting('site_title', 'Mori Capital Management');

// Alternate language links (hreflang)
$altUrlEn = $scheme . '://' . $host . $canonicalPath . '?lang=en';
$altUrlDe = $scheme . '://' . $host . $canonicalPath . '?lang=de';

// Admin-managed custom head code (analytics, GTM, pixel, custom meta tags, etc.)
$customHead = setting('custom_head_code', '');
?>
<!DOCTYPE html>
<html lang="<?= e(I18n::locale()) ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
	<meta name="description" content="<?= e($desc) ?>">
	<meta name="keywords" content="<?= e($kw) ?>">
	<meta name="author" content="Mori Capital Management">
	<title><?= e($title) ?></title>

	<!-- Canonical + hreflang (multilingual SEO) -->
	<link rel="canonical" href="<?= e($canonical) ?>">
	<link rel="alternate" hreflang="en"     href="<?= e($altUrlEn) ?>">
	<link rel="alternate" hreflang="de"     href="<?= e($altUrlDe) ?>">
	<link rel="alternate" hreflang="x-default" href="<?= e($altUrlEn) ?>">

	<!-- Open Graph (Facebook / LinkedIn / WhatsApp preview) -->
	<meta property="og:site_name"   content="<?= e($siteName) ?>">
	<meta property="og:type"        content="<?= e($ogType) ?>">
	<meta property="og:title"       content="<?= e($title) ?>">
	<meta property="og:description" content="<?= e($desc) ?>">
	<meta property="og:url"         content="<?= e($canonical) ?>">
	<meta property="og:image"       content="<?= e($ogImage) ?>">
	<meta property="og:locale"      content="<?= e(I18n::locale() === 'de' ? 'de_DE' : 'en_GB') ?>">

	<!-- Twitter Card -->
	<meta name="twitter:card"        content="summary_large_image">
	<meta name="twitter:title"       content="<?= e($title) ?>">
	<meta name="twitter:description" content="<?= e($desc) ?>">
	<meta name="twitter:image"       content="<?= e($ogImage) ?>">

	<!-- Favicons -->
	<link rel="icon" type="image/png" sizes="192x192" href="<?= asset('assets/images/android-icon-192x192.png') ?>">
	<link rel="apple-touch-icon" sizes="192x192" href="<?= asset('assets/images/android-icon-192x192.png') ?>">
	<link rel="shortcut icon" type="image/png" href="<?= asset('assets/images/android-icon-192x192.png') ?>">

	<!-- Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com/">
	<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

	<!-- Theme stylesheets -->
	<link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet" media="screen">
	<link href="<?= asset('css/slicknav.min.css') ?>" rel="stylesheet">
	<link rel="stylesheet" href="<?= asset('css/swiper-bundle.min.css') ?>">
	<link href="<?= asset('css/all.min.css') ?>" rel="stylesheet" media="screen">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer">
	<link href="<?= asset('css/animate.css') ?>" rel="stylesheet">
	<link rel="stylesheet" href="<?= asset('css/magnific-popup.css') ?>">
	<link rel="stylesheet" href="<?= asset('css/mousecursor.css') ?>">
	<link href="<?= asset('css/custom.css') ?>" rel="stylesheet" media="screen">

	<!-- Mori override stylesheet -->
	<link href="<?= asset('css/mori.css') ?>" rel="stylesheet" media="screen">

	<?php if ($ga): ?>
	<!-- Google Analytics (Settings → SEO → Google Analytics ID) -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
	<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
	<?php endif; ?>

	<?php if ($customHead): ?>
	<!-- Admin-managed custom head code (Settings → Custom Code) -->
	<?= $customHead ?>
	<?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">

	<!-- Preloader -->
	<div class="preloader">
		<div class="loading-container">
			<div class="loading"></div>
			<div id="loading-icon"><img src="<?= asset('assets/images/android-icon-192x192.png') ?>" alt="Mori Capital" style="border-radius:50%;width:64px;height:64px;object-fit:cover;box-shadow:0 6px 18px rgba(8,18,33,.18);"></div>
		</div>
	</div>
