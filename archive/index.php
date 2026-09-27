<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/private/helpers.php';

try {
    $site = dataRequest(['request' => 'site-settings']);
    $slug = $_GET['slug'] ?? null;
    $post = $slug === null ? null : dataRequest(['request' => 'post-by-permalink', 'permalink' => $slug]);
    $posts = $slug === null ? dataRequest(['request' => 'published-posts']) : [];
    if ($slug !== null && $post === null) http_response_code(404);
} catch (Throwable $error) {
    error_log('Easy Blog: ' . $error->getMessage()); http_response_code(500);
    $site = ['title' => 'Easy Blog', 'description' => '']; $pageTitle = 'Temporarily unavailable';
    require dirname(__DIR__) . '/includes/header.php';
    ?><h1>Temporarily unavailable</h1><p>Please try again later.</p><?php
    require dirname(__DIR__) . '/includes/footer.php'; exit;
}
$pageTitle = $slug !== null && $post === null ? 'Post not found' : ($post['title'] ?? 'Archive');
$metaDescription = $post['description'] ?? '';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php if ($slug !== null && $post === null): ?>
    <h1>Post not found</h1><p><a href="/archive/">Browse the archive</a></p>
<?php elseif ($post): ?>
<article>
    <p class="meta"><time datetime="<?= escape($post['date']) ?>"><?= escape(displayDate($post['date'], $site['timeZone'])) ?></time><?php if ($post['catName'] !== ''): ?> · <?= escape($post['catName']) ?><?php endif; ?></p>
    <h1><?= escape($post['title']) ?></h1>
    <p class="description"><?= escape($post['description']) ?></p>
    <div class="content">
        <?php foreach (preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", $post['content'])) as $block): ?>
            <?php if (trim($block) === '') continue; ?>
            <?php if (preg_match('/^(#{1,6})\s+([^\n]+)\n?$/', $block, $match)): $level = min(strlen($match[1]) + 1, 6); ?>
                <<?= 'h' . $level ?>><?= escape($match[2]) ?></<?= 'h' . $level ?>>
            <?php else: ?><p><?= nl2br(escape($block)) ?></p><?php endif; ?>
        <?php endforeach; ?>
    </div>
</article>
<p><a href="/archive/">All posts</a></p>
<?php else: ?>
<h1>Archive</h1>
<?php if (!$posts): ?><p>No posts yet.</p><?php endif; ?>
<?php foreach ($posts as $item): ?>
<article>
    <p class="meta"><time datetime="<?= escape($item['date']) ?>"><?= escape(displayDate($item['date'], $site['timeZone'])) ?></time><?php if ($item['catName'] !== ''): ?> · <?= escape($item['catName']) ?><?php endif; ?></p>
    <h2><a href="<?= escape(postUrl($item)) ?>"><?= escape($item['title']) ?></a></h2>
    <p><?= escape($item['description']) ?></p>
</article>
<?php endforeach; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
