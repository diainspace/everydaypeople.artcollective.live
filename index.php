<?php
declare(strict_types=1);
require __DIR__ . '/backend/private/helpers.php';

try {
    $site = dataRequest(['request' => 'site-settings']);
    $posts = dataRequest(['request' => 'published-posts']);
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $pages = max(1, (int) ceil(count($posts) / $site['postsPerPage']));
    if ($page === false || $page > $pages) {
        http_response_code(404); $pageTitle = 'Page not found';
        require __DIR__ . '/includes/header.php';
        ?><h1>Page not found</h1><p><a href="/">Return to the blog</a></p><?php
        require __DIR__ . '/includes/footer.php'; exit;
    }
    $visible = array_slice($posts, ($page - 1) * $site['postsPerPage'], $site['postsPerPage']);
} catch (Throwable $error) {
    error_log('Easy Blog: ' . $error->getMessage()); http_response_code(500);
    $site = ['title' => 'Easy Blog', 'description' => '']; $pageTitle = 'Temporarily unavailable';
    require __DIR__ . '/includes/header.php';
    ?><h1>Temporarily unavailable</h1><p>Please try again later.</p><?php
    require __DIR__ . '/includes/footer.php'; exit;
}
$pageTitle = 'Blog';
require __DIR__ . '/includes/header.php';
?>
<h1>Our blog</h1>
<?php if (!$visible): ?><p>No posts yet. Please check back soon.</p><?php endif; ?>
<?php foreach ($visible as $post): ?>
<article>
    <p class="meta"><time datetime="<?= escape($post['date']) ?>"><?= escape(displayDate($post['date'], $site['timeZone'])) ?></time><?php if ($post['catName'] !== ''): ?> · <?= escape($post['catName']) ?><?php endif; ?></p>
    <h2><a href="<?= escape(postUrl($post)) ?>"><?= escape($post['title']) ?></a></h2>
    <p><?= escape($post['description']) ?></p>
    <a href="<?= escape(postUrl($post)) ?>">Read post<span class="visually-hidden">: <?= escape($post['title']) ?></span></a>
</article>
<?php endforeach; ?>
<?php if ($pages > 1): ?>
<nav aria-label="Blog pages">
    <?php if ($page > 1): ?><a href="/?page=<?= $page - 1 ?>">Newer posts</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="/?page=<?= $page + 1 ?>">Older posts</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
