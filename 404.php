<?php
declare(strict_types=1);

require __DIR__ . '/backend/private/helpers.php';

http_response_code(404);

try {
    $site = dataRequest(['request' => 'site-settings']);
} catch (Throwable $error) {
    error_log('Easy Blog: ' . $error->getMessage());
    $site = [
        'title' => 'Everyday People Art Collective',
        'description' => '',
    ];
}

$pageTitle = 'Page not found';
$metaDescription = 'The requested page could not be found.';

require __DIR__ . '/includes/header.php';
?>
<h1>Page not found</h1>
<p>The page you requested does not exist or may have moved.</p>
<p><a href="/">Return to the blog</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
