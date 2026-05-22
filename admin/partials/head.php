<?php
use function Mori\e;
use function Mori\asset;

$adminPage = $adminPage ?? [];
$title = ($adminPage['title'] ?? 'Admin') . ' — Mori Capital CMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="icon" type="image/png" sizes="192x192" href="<?= asset('assets/images/android-icon-192x192.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com/">
<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= asset('admin/assets/admin.css') ?>">
</head>
<body>
