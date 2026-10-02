<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$pageTitle ??= 'Blog';
$metaDescription ??= $site['description'] ?? '';
$mainClass ??= '';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentNav = is_string($requestPath) && str_starts_with($requestPath, '/stories/') ? 'stories'
    : (is_string($requestPath) && str_starts_with($requestPath, '/about/') ? 'about' : '');
$styleVersion = (string) filemtime(dirname(__DIR__) . '/assets/site.css');
$headlineScriptVersion = (string) filemtime(dirname(__DIR__) . '/assets/fit-headline.js');
if (!isset($websiteContent)) {
    try {
        $websiteContent = dataRequest(['request' => 'website-content']);
    } catch (Throwable $error) {
        error_log('Easy Blog: ' . $error->getMessage());
        $websiteContent = ['items' => []];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> · <?= escape($site['title']) ?></title>
    <meta name="description" content="<?= escape($metaDescription ?: $site['description']) ?>">
    <link rel="stylesheet" href="/assets/site.css?v=<?= rawurlencode($styleVersion) ?>">
    <script src="/assets/fit-headline.js?v=<?= rawurlencode($headlineScriptVersion) ?>" defer></script>
    <?php if (isset($pageScript) && is_string($pageScript)): $pageScriptVersion = (string) filemtime(dirname(__DIR__) . $pageScript); ?><script src="<?= escape($pageScript) ?>?v=<?= rawurlencode($pageScriptVersion) ?>" defer></script><?php endif; ?>
</head>
<body>
<div class="site">
    <header class="site-header">
        <a class="wordmark" href="/" aria-label="<?= escape($site['title']) ?> home"><span>Everyday People</span><span>Art Collective</span></a>
        <nav aria-label="Main navigation"><?php if (websiteContentValue($websiteContent, 'promote_archive', '0') === '1'): ?><a href="/stories/"<?= $currentNav === 'stories' ? ' aria-current="page"' : '' ?>>Stories</a><?php endif; ?><a href="/about/"<?= $currentNav === 'about' ? ' aria-current="page"' : '' ?>>About</a></nav>
    </header>
    <main<?= $mainClass !== '' ? ' class="' . escape($mainClass) . '"' : '' ?>>
