<?php
declare(strict_types=1);

require dirname(__DIR__) . '/backend/private/helpers.php';

try {
    $site = dataRequest(['request' => 'site-settings']);
    $websiteContent = dataRequest(['request' => 'website-content']);
} catch (Throwable $error) {
    error_log('Easy Blog: ' . $error->getMessage());
    http_response_code(500);
    $site = ['title' => 'Easy Blog', 'description' => ''];
    $pageTitle = 'Temporarily unavailable';
    require dirname(__DIR__) . '/includes/header.php';
    ?><h1>Temporarily unavailable</h1><p>Please try again later.</p><?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$pageTitle = websiteContentValue($websiteContent, 'about_title', 'About');
$aboutBody = websiteContentValue($websiteContent, 'about_body');
$mainClass = 'about-page';
require dirname(__DIR__) . '/includes/header.php';
?>
<header class="page-intro"><p class="section-number">Everyday People / Art Collective</p><h1><?= escape($pageTitle) ?></h1></header>
<article class="article-body">
    <?php if ($aboutBody === ''): ?>
        <p class="article-lead">About page content is coming soon.</p>
    <?php else: ?>
        <?php foreach (preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", $aboutBody)) as $block): ?>
            <?php if (trim($block) === '') continue; ?>
            <?php if (preg_match('/^(#{1,6})\s+([^\n]+)\n?$/', $block, $match)): $level = min(strlen($match[1]) + 1, 6); ?>
                <<?= 'h' . $level ?>><?= escape($match[2]) ?></<?= 'h' . $level ?>>
            <?php else: ?><p><?= nl2br(escape($block)) ?></p><?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</article>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
