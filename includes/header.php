<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$pageTitle ??= 'Blog';
$metaDescription ??= $site['description'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> · <?= escape($site['title']) ?></title>
    <meta name="description" content="<?= escape($metaDescription ?: $site['description']) ?>">
    <link rel="stylesheet" href="/assets/site.css">
</head>
<body>
<div class="site">
    <header class="site-header">
        <a class="site-name" href="/"><?= escape($site['title']) ?></a>
        <p><?= escape($site['description']) ?></p>
        <nav aria-label="Main navigation"><a href="/">Blog</a><a href="/archive/">Archive</a><a href="/admin/">Admin</a></nav>
    </header>
    <main>
