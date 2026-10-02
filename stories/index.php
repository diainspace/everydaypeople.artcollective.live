<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/private/helpers.php';

try {
    $site = dataRequest(['request' => 'site-settings']);
    $slug = $_GET['slug'] ?? null;
    $post = $slug === null ? null : dataRequest(['request' => 'post-by-permalink', 'permalink' => $slug]);
    $posts = $slug === null ? dataRequest(['request' => 'archive-posts']) : [];
    $websiteContent = dataRequest(['request' => 'website-content']);
    if ($slug !== null && $post === null) http_response_code(404);
} catch (Throwable $error) {
    error_log('Easy Blog: ' . $error->getMessage()); http_response_code(500);
    $site = ['title' => 'Easy Blog', 'description' => '']; $pageTitle = 'Temporarily unavailable';
    require dirname(__DIR__) . '/includes/header.php';
    ?><h1>Temporarily unavailable</h1><p>Please try again later.</p><?php
    require dirname(__DIR__) . '/includes/footer.php'; exit;
}
$storiesPageTitle = websiteContentValue($websiteContent, 'stories_page_title', "The\nArchive");
$pageTitle = $slug !== null && $post === null ? 'Post not found' : ($post['title'] ?? str_replace("\n", ' ', $storiesPageTitle));
$metaDescription = $post['description'] ?? '';
$mainClass = $post ? 'article-page' : 'archive-page';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php if ($slug !== null && $post === null): ?>
    <div class="error-content"><h1>Post not found</h1><p><a href="/stories/">Browse the archive</a></p></div>
<?php elseif ($post): ?>
<header class="article-header">
    <div><p class="section-number">Story / <?= escape($post['catName'] ?: 'Uncategorized') ?></p><h1><?= escape($post['title']) ?></h1></div>
    <div class="article-dek"><p><?= escape($post['description']) ?></p><time datetime="<?= escape($post['date']) ?>"><?= escape(displayDate($post['date'], $site['timeZone'])) ?></time></div>
</header>
<?php $coverPhoto = $post['coverPhoto']; ?>
<figure class="article-hero">
    <img src="<?= escape($coverPhoto['src'] ?? '/assets/images/placeholder.gif') ?>" alt="<?= escape($coverPhoto['alt'] ?? '') ?>">
    <?php if (($coverPhoto['caption'] ?? '') !== ''): ?><figcaption><?= escape($coverPhoto['caption']) ?></figcaption><?php endif; ?>
</figure>
<article class="article-body">
    <?php $contentBlocks = array_values(array_filter(preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", $post['content'])), static fn (string $block): bool => trim($block) !== '')); ?>
    <?php foreach ($contentBlocks as $index => $block): ?>
        <?php if (preg_match('/^(#{1,6})\s+([^\n]+)\n?$/', $block, $match)): $level = min(strlen($match[1]) + 1, 6); ?>
            <<?= 'h' . $level ?>><?= escape($match[2]) ?></<?= 'h' . $level ?>>
        <?php else: ?><p<?= $index === 0 ? ' class="article-lead"' : '' ?>><?= nl2br(escape($block)) ?></p><?php endif; ?>
    <?php endforeach; ?>
</article>
<p class="article-back"><a href="/stories/">← More Stories</a></p>
<?php else: ?>
<header class="page-intro"><p class="section-number">All stories / <?= escape((new DateTimeImmutable('now', new DateTimeZone($site['timeZone'])))->format('Y')) ?></p><h1><?= nl2br(escape($storiesPageTitle)) ?></h1></header>
<?php if (!$posts): ?><p class="empty-message">No stories yet.</p><?php endif; ?>
<ol class="archive-list">
<?php foreach ($posts as $index => $item): $blogPhoto = $item['blogPhoto']; ?>
    <li><a href="<?= escape(postUrl($item)) ?>">
        <span class="archive-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <span class="archive-art"><img src="<?= escape($blogPhoto['src'] ?? '/assets/images/placeholder.gif') ?>" alt="" loading="lazy"></span>
        <span class="archive-copy"><strong><?= escape($item['title']) ?></strong><small><?= escape($item['catName'] ?: 'Story') ?> / <?= escape(displayDate($item['date'], $site['timeZone'])) ?></small></span>
        <span class="archive-arrow" aria-hidden="true">↗</span>
    </a></li>
<?php endforeach; ?>
</ol>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
