<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

require dirname(__DIR__, 2) . '/private/posts.php';

try {
    $raw = @file_get_contents(dirname(__DIR__, 2) . '/private/data/easy-blog.json');
    if ($raw === false) {
        throw new RuntimeException('Blog data unavailable.');
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['posts']) || !is_array($data['posts']) || !array_is_list($data['posts'])) {
        throw new RuntimeException('Invalid blog data.');
    }
    echo json_encode(['posts' => publishedPosts($data['posts'], new DateTimeImmutable('now'))], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Easy Blog posts: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load posts.']);
}
