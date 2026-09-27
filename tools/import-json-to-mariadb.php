<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/backend/private/validation.php';

function prompt(string $label, string $default): string
{
    fwrite(STDOUT, $label . ' [' . $default . ']: ');
    $value = trim((string) fgets(STDIN));
    return $value === '' ? $default : $value;
}

function secretPrompt(string $label): string
{
    fwrite(STDOUT, $label . ': ');
    $settings = shell_exec('stty -g');
    shell_exec('stty -echo');
    $value = rtrim((string) fgets(STDIN), "\r\n");
    if ($settings !== null) shell_exec('stty ' . escapeshellarg(trim($settings)));
    fwrite(STDOUT, "\n");
    return $value;
}

function jsonDocument(string $name): array
{
    $path = dirname(__DIR__) . '/backend/private/data/' . $name . '.json';
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Unable to read ' . $path);
    $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    validateDocument($name, $document);
    return $document;
}

function utcDate(?string $value): ?string
{
    if ($value === null) return null;
    return (new DateTimeImmutable($value))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s.u');
}

function photoValue(?array $photo, string $field): ?string
{
    return $photo === null ? null : ($photo[$field] ?? '');
}

try {
    $host = prompt('Database host', 'localhost');
    $port = prompt('Database port', '3306');
    $database = prompt('Database name', 'acl_easyblog');
    $username = prompt('Database user', 'acl_easyblog_app');
    $password = secretPrompt('Database password');

    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new InvalidArgumentException('Invalid database port.');
    }

    $blog = jsonDocument('easy-blog');
    $account = jsonDocument('easy-admin');

    $postIds = [];
    $permalinks = [];
    $categoryDescriptions = [];
    foreach ($blog['posts'] as $post) {
        if (isset($postIds[$post['id']])) throw new InvalidArgumentException('Duplicate post ID: ' . $post['id']);
        if (isset($permalinks[$post['permaLink']])) throw new InvalidArgumentException('Duplicate permalink: ' . $post['permaLink']);
        if ($post['authorId'] !== $account['admin']['id']) throw new InvalidArgumentException('Unknown post author: ' . $post['authorId']);
        $postIds[$post['id']] = true;
        $permalinks[$post['permaLink']] = true;
        if ($post['catName'] !== '') {
            if (isset($categoryDescriptions[$post['catName']]) && $categoryDescriptions[$post['catName']] !== $post['catDesc']) {
                throw new InvalidArgumentException('Category descriptions disagree for: ' . $post['catName']);
            }
            $categoryDescriptions[$post['catName']] = $post['catDesc'];
        }
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );

    $migration = $pdo->query("SELECT version FROM schema_migrations WHERE version = '001_initial_schema'")->fetchColumn();
    if ($migration !== '001_initial_schema') {
        throw new RuntimeException('Migration 001_initial_schema has not been applied.');
    }

    $pdo->beginTransaction();

    $adminStatement = $pdo->prepare(
        'INSERT INTO administrators (id, username, short_name, password_hash, password_updated_at)
         VALUES (:id, :username, :short_name, :password_hash, :password_updated_at)
         ON DUPLICATE KEY UPDATE username = VALUES(username), short_name = VALUES(short_name),
             password_hash = VALUES(password_hash), password_updated_at = VALUES(password_updated_at)'
    );
    $adminStatement->execute([
        'id' => $account['admin']['id'],
        'username' => $account['admin']['username'],
        'short_name' => $account['admin']['shortName'],
        'password_hash' => $account['admin']['passwordHash'],
        'password_updated_at' => utcDate($account['admin']['passwordUpdatedAt']),
    ]);

    $siteStatement = $pdo->prepare(
        'INSERT INTO site_settings (id, title, description, time_zone, posts_per_page)
         VALUES (1, :title, :description, :time_zone, :posts_per_page)
         ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description),
             time_zone = VALUES(time_zone), posts_per_page = VALUES(posts_per_page)'
    );
    $siteStatement->execute([
        'title' => $account['site']['title'],
        'description' => $account['site']['description'],
        'time_zone' => $account['site']['timeZone'],
        'posts_per_page' => $account['site']['postsPerPage'],
    ]);

    $categoryUpsert = $pdo->prepare(
        'INSERT INTO categories (name, description) VALUES (:name, :description)
         ON DUPLICATE KEY UPDATE description = VALUES(description)'
    );
    $categorySelect = $pdo->prepare('SELECT id FROM categories WHERE name = :name');
    $permalinkConflict = $pdo->prepare('SELECT id FROM posts WHERE permalink = :permalink AND id <> :id');
    $postStatement = $pdo->prepare(
        'INSERT INTO posts (
            id, author_id, category_id, status, published_at, expires_at, title, description, content,
            cover_photo_src, cover_photo_alt, cover_photo_caption,
            blog_photo_src, blog_photo_alt, blog_photo_caption, permalink, updated_at
        ) VALUES (
            :id, :author_id, :category_id, :status, :published_at, :expires_at, :title, :description, :content,
            :cover_photo_src, :cover_photo_alt, :cover_photo_caption,
            :blog_photo_src, :blog_photo_alt, :blog_photo_caption, :permalink, :updated_at
        ) ON DUPLICATE KEY UPDATE
            author_id = VALUES(author_id), category_id = VALUES(category_id), status = VALUES(status),
            published_at = VALUES(published_at), expires_at = VALUES(expires_at), title = VALUES(title),
            description = VALUES(description), content = VALUES(content),
            cover_photo_src = VALUES(cover_photo_src), cover_photo_alt = VALUES(cover_photo_alt),
            cover_photo_caption = VALUES(cover_photo_caption), blog_photo_src = VALUES(blog_photo_src),
            blog_photo_alt = VALUES(blog_photo_alt), blog_photo_caption = VALUES(blog_photo_caption),
            permalink = VALUES(permalink), updated_at = VALUES(updated_at)'
    );

    foreach ($blog['posts'] as $post) {
        $permalinkConflict->execute(['permalink' => $post['permaLink'], 'id' => $post['id']]);
        if ($permalinkConflict->fetchColumn() !== false) {
            throw new RuntimeException('The database already uses permalink: ' . $post['permaLink']);
        }

        $categoryId = null;
        if ($post['catName'] !== '') {
            $categoryUpsert->execute(['name' => $post['catName'], 'description' => $post['catDesc']]);
            $categorySelect->execute(['name' => $post['catName']]);
            $categoryId = $categorySelect->fetchColumn();
            if ($categoryId === false) throw new RuntimeException('Unable to resolve category.');
        }

        $postStatement->execute([
            'id' => $post['id'],
            'author_id' => $post['authorId'],
            'category_id' => $categoryId,
            'status' => $post['status'],
            'published_at' => utcDate($post['date']),
            'expires_at' => utcDate($post['expDate']),
            'title' => $post['title'],
            'description' => $post['description'],
            'content' => $post['content'],
            'cover_photo_src' => photoValue($post['coverPhoto'], 'src'),
            'cover_photo_alt' => photoValue($post['coverPhoto'], 'alt'),
            'cover_photo_caption' => photoValue($post['coverPhoto'], 'caption'),
            'blog_photo_src' => photoValue($post['blogPhoto'], 'src'),
            'blog_photo_alt' => photoValue($post['blogPhoto'], 'alt'),
            'blog_photo_caption' => photoValue($post['blogPhoto'], 'caption'),
            'permalink' => $post['permaLink'],
            'updated_at' => utcDate($post['updatedAt']),
        ]);
    }

    $pdo->commit();
    fwrite(STDOUT, sprintf(
        "Imported 1 administrator, 1 site record, and %d post%s into %s.\n",
        count($blog['posts']),
        count($blog['posts']) === 1 ? '' : 's',
        $database,
    ));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Import failed: ' . $error->getMessage() . "\n");
    exit(1);
}
