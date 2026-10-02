<?php
declare(strict_types=1);
require __DIR__ . '/backend/private/helpers.php';

try {
    $site = dataRequest(['request' => 'site-settings']);
    $websiteContent = dataRequest(['request' => 'website-content']);
    $posts = dataRequest(['request' => 'published-posts']);
    $promoteArchive = websiteContentValue($websiteContent, 'promote_archive', '0') === '1';
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $pages = $promoteArchive ? max(1, (int) ceil(count($posts) / $site['postsPerPage'])) : 1;
    if (!$promoteArchive) $page = 1;
    if ($page === false || $page > $pages) {
        http_response_code(404); $pageTitle = 'Page not found';
        require __DIR__ . '/includes/header.php';
        ?><h1>Page not found</h1><p><a href="/">Return to the blog</a></p><?php
        require __DIR__ . '/includes/footer.php'; exit;
    }
    $visible = $promoteArchive
        ? array_slice($posts, ($page - 1) * $site['postsPerPage'], $site['postsPerPage'])
        : array_slice($posts, 0, 1);
} catch (Throwable $error) {
    error_log('Easy Blog: ' . $error->getMessage()); http_response_code(500);
    $site = ['title' => 'Easy Blog', 'description' => '']; $pageTitle = 'Temporarily unavailable';
    require __DIR__ . '/includes/header.php';
    ?><h1>Temporarily unavailable</h1><p>Please try again later.</p><?php
    require __DIR__ . '/includes/footer.php'; exit;
}
$pageTitle = 'Blog';
$mainClass = 'home-page';
require __DIR__ . '/includes/header.php';
?>
<section class="intro" aria-labelledby="intro-title">
    <p class="section-number">Stories / <?= escape((new DateTimeImmutable('now', new DateTimeZone($site['timeZone'])))->format('Y')) ?></p>
    <h1 id="intro-title" data-fit-headline><?= nl2br(escape(websiteContentValue($websiteContent, 'home_main_statement', 'Art lives with people.'))) ?></h1>
    <div class="intro-note">
        <?php if ($promoteArchive): ?>
            <p><?= escape(websiteContentValue($websiteContent, 'home_supporting_text', 'Art, stories, and updates.')) ?></p>
            <a class="solid-link" href="/stories/"><?= escape(websiteContentValue($websiteContent, 'home_archive_link_text', 'Enter the archive')) ?></a>
        <?php else: ?>
            <p><?= escape(websiteContentValue($websiteContent, 'alternate_promotion_text', 'Art, stories, and updates.')) ?></p>
            <?php if (websiteContentValue($websiteContent, 'alternate_show_button', '0') === '1'): ?>
                <a class="solid-link" href="<?= escape(websiteContentValue($websiteContent, 'alternate_button_url', '/about/')) ?>"><?= escape(websiteContentValue($websiteContent, 'alternate_button_text', 'Learn more')) ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php $featured = $visible[0] ?? null; ?>
<?php if ($featured): $featuredPhoto = $featured['coverPhoto']; ?>
<section aria-label="Featured story">
    <article class="post-card post-card-featured">
        <a class="post-card-art" href="<?= escape(postUrl($featured)) ?>" aria-label="Read <?= escape($featured['title']) ?>">
            <img src="<?= escape($featuredPhoto['src'] ?? '/assets/images/placeholder.gif') ?>" alt="<?= escape($featuredPhoto['alt'] ?? '') ?>">
            <span class="image-number">01</span>
        </a>
        <div class="post-card-copy">
            <p class="meta"><span><?= escape($featured['catName'] ?: 'Story') ?></span><time datetime="<?= escape($featured['date']) ?>"><?= escape(displayDate($featured['date'], $site['timeZone'])) ?></time></p>
            <h2><a href="<?= escape(postUrl($featured)) ?>"><?= escape($featured['title']) ?></a></h2>
            <p class="dek"><?= escape($featured['description']) ?></p>
            <a class="text-link" href="<?= escape(postUrl($featured)) ?>">Read the story <span aria-hidden="true">↗</span></a>
        </div>
    </article>
</section>
<?php endif; ?>

<?php if ($promoteArchive): ?><section class="latest" aria-labelledby="latest-title">
    <header class="section-heading"><h2 id="latest-title">More Stories</h2></header>
    <?php if (!$visible): ?><p class="empty-message">No stories yet. Please check back soon.</p><?php endif; ?>
    <?php if (count($visible) > 1): ?><div class="post-grid">
        <?php foreach (array_slice($visible, 1) as $index => $post): $blogPhoto = $post['blogPhoto']; ?>
        <article class="post-card">
            <a class="post-card-art" href="<?= escape(postUrl($post)) ?>" aria-label="Read <?= escape($post['title']) ?>">
                <img src="<?= escape($blogPhoto['src'] ?? '/assets/images/placeholder.gif') ?>" alt="<?= escape($blogPhoto['alt'] ?? '') ?>" loading="lazy">
                <span class="image-number"><?= str_pad((string) ($index + 2), 2, '0', STR_PAD_LEFT) ?></span>
                <span class="story-image-overlay"><strong><?= escape($post['title']) ?></strong></span>
            </a>
            <div class="post-card-copy">
                <p class="meta"><span><?= escape($post['catName'] ?: 'Story') ?></span><time datetime="<?= escape($post['date']) ?>"><?= escape(displayDate($post['date'], $site['timeZone'])) ?></time></p>
                <h2><a href="<?= escape(postUrl($post)) ?>"><?= escape($post['title']) ?></a></h2>
                <p class="dek"><?= escape($post['description']) ?></p>
                <a class="text-link" href="<?= escape(postUrl($post)) ?>">Read the story <span aria-hidden="true">↗</span></a>
            </div>
        </article>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section><?php endif; ?>
<?php if ($promoteArchive && $pages > 1): ?>
<nav class="pagination" aria-label="Story pages">
    <?php if ($page > 1): ?><a href="/?page=<?= $page - 1 ?>">Newer posts</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="/?page=<?= $page + 1 ?>">Older posts</a><?php endif; ?>
</nav>
<?php endif; ?>
<section class="statement">
    <p class="section-number"><?= escape(websiteContentValue($websiteContent, 'home_about_label', 'What we believe')) ?></p>
    <p class="statement-copy"><a class="statement-link" href="/about/"><?= escape(websiteContentValue($websiteContent, 'home_belief_statement')) ?> <span aria-hidden="true">&gt;&gt;</span></a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
