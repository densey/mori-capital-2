<?php
/**
 * <head> + opening <body> + preloader.
 * Expects: $page = ['title','description','keywords','body_class']
 */
use Mori\I18n;
use function Mori\e;
use function Mori\setting;

$page = $page ?? [];
$title = $page['title'] ?? (setting('seo_default_title') ?? 'Mori Capital Management');
$desc  = $page['description'] ?? (setting('seo_default_desc') ?? '');
$kw    = $page['keywords'] ?? 'Mori Capital, EEMEA, Emerging Europe, Ottoman Fund, asset management, Malta, MFSA';
$bodyClass = $page['body_class'] ?? '';
$ga = setting('google_analytics_id', '');
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

	<!-- Favicons -->
	<link rel="icon" type="image/png" sizes="192x192" href="<?= \Mori\asset('assets/images/android-icon-192x192.png') ?>">
	<link rel="apple-touch-icon" sizes="192x192" href="<?= \Mori\asset('assets/images/android-icon-192x192.png') ?>">
	<link rel="shortcut icon" type="image/png" href="<?= \Mori\asset('assets/images/android-icon-192x192.png') ?>">

	<!-- Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com/">
	<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

	<!-- Theme stylesheets -->
	<link href="<?= \Mori\asset('css/bootstrap.min.css') ?>" rel="stylesheet" media="screen">
	<link href="<?= \Mori\asset('css/slicknav.min.css') ?>" rel="stylesheet">
	<link rel="stylesheet" href="<?= \Mori\asset('css/swiper-bundle.min.css') ?>">
	<link href="<?= \Mori\asset('css/all.min.css') ?>" rel="stylesheet" media="screen">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer">
	<link href="<?= \Mori\asset('css/animate.css') ?>" rel="stylesheet">
	<link rel="stylesheet" href="<?= \Mori\asset('css/magnific-popup.css') ?>">
	<link rel="stylesheet" href="<?= \Mori\asset('css/mousecursor.css') ?>">
	<link href="<?= \Mori\asset('css/custom.css') ?>" rel="stylesheet" media="screen">

	<!-- Mori override stylesheet -->
	<link href="<?= \Mori\asset('css/mori.css') ?>" rel="stylesheet" media="screen">

	<?php if ($ga): ?>
	<!-- Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
	<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
	<?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">

	<!-- Preloader -->
	<div class="preloader">
		<div class="loading-container">
			<div class="loading"></div>
			<div id="loading-icon"><img src="<?= \Mori\asset('assets/images/android-icon-192x192.png') ?>" alt="Mori Capital" style="border-radius:50%;width:64px;height:64px;object-fit:cover;box-shadow:0 6px 18px rgba(8,18,33,.18);"></div>
		</div>
	</div>
