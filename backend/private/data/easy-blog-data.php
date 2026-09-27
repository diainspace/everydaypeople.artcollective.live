<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/validation.php';

function easyBlogData(array $request): string
{
    try {
        $allowed = ['site-settings', 'published-posts', 'post-by-permalink', 'admin-account', 'admin-posts', 'save-post', 'update-admin-password'];
        $name = $request['request'] ?? null;
        if (!is_string($name) || !in_array($name, $allowed, true)) {
            throw new InvalidArgumentException('Unknown data request.');
        }

        $pdo = easyBlogDatabase();
        $data = match ($name) {
            'site-settings' => easyBlogSiteSettings($pdo),
            'published-posts' => easyBlogPublishedPosts($pdo),
            'post-by-permalink' => easyBlogPostByPermalink($pdo, $request),
            'admin-account' => easyBlogAdminAccount($pdo),
            'admin-posts' => easyBlogAdminPosts($pdo),
            'save-post' => easyBlogSavePost($pdo, $request),
            'update-admin-password' => easyBlogUpdateAdminPassword($pdo, $request),
        };
        return json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        error_log('Easy Blog data: ' . $error->getMessage());
        return json_encode([
            'ok' => false,
            'error' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'The data request could not be completed.',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

function easyBlogDatabase(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    $path = dirname(__DIR__) . '/config/database.php';
    if (!is_file($path)) throw new RuntimeException('Database configuration is missing.');
    $config = require $path;
    foreach (['host', 'port', 'name', 'user', 'password'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key])) throw new RuntimeException('Invalid database configuration.');
    }
    $connection = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
    );
    $connection->exec("SET time_zone = '+00:00'");
    return $connection;
}

function easyBlogDatabaseDate(?string $value): ?string
{
    return $value === null ? null : (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format(DATE_ATOM);
}

function easyBlogUtcDate(?string $value): ?string
{
    return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
}

function easyBlogPostSelect(): string
{
    return 'SELECT p.*, c.name AS category_name, c.description AS category_description FROM posts p LEFT JOIN categories c ON c.id = p.category_id';
}

function easyBlogPostFromRow(array $row): array
{
    $photo = static function (string $prefix) use ($row): ?array {
        if ($row[$prefix . '_src'] === null) return null;
        return ['src' => $row[$prefix . '_src'], 'alt' => $row[$prefix . '_alt'] ?? '', 'caption' => $row[$prefix . '_caption'] ?? ''];
    };
    return [
        'id' => $row['id'], 'authorId' => $row['author_id'], 'status' => $row['status'],
        'date' => easyBlogDatabaseDate($row['published_at']), 'title' => $row['title'],
        'description' => $row['description'], 'content' => $row['content'],
        'coverPhoto' => $photo('cover_photo'), 'blogPhoto' => $photo('blog_photo'),
        'permaLink' => $row['permalink'], 'expDate' => easyBlogDatabaseDate($row['expires_at']),
        'catName' => $row['category_name'] ?? '', 'catDesc' => $row['category_description'] ?? '',
        'updatedAt' => easyBlogDatabaseDate($row['updated_at']),
    ];
}

function easyBlogSiteSettings(PDO $pdo): array
{
    $row = $pdo->query('SELECT title, description, time_zone, posts_per_page FROM site_settings WHERE id = 1')->fetch();
    if (!$row) throw new RuntimeException('Site settings are missing.');
    new DateTimeZone($row['time_zone']);
    return ['title' => $row['title'], 'description' => $row['description'], 'timeZone' => $row['time_zone'], 'postsPerPage' => (int) $row['posts_per_page']];
}

function easyBlogPublishedPosts(PDO $pdo): array
{
    $statement = $pdo->query(easyBlogPostSelect() . " WHERE p.status = 'published' AND p.published_at <= UTC_TIMESTAMP(6) AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP(6)) ORDER BY p.published_at DESC");
    return array_map('easyBlogPostFromRow', $statement->fetchAll());
}

function easyBlogPostByPermalink(PDO $pdo, array $request): ?array
{
    $permalink = $request['permalink'] ?? null;
    if (!is_string($permalink) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $permalink) !== 1) return null;
    $statement = $pdo->prepare(easyBlogPostSelect() . " WHERE p.permalink = :permalink AND p.status = 'published' AND p.published_at <= UTC_TIMESTAMP(6) AND (p.expires_at IS NULL OR p.expires_at > UTC_TIMESTAMP(6)) LIMIT 1");
    $statement->execute(['permalink' => $permalink]);
    $row = $statement->fetch();
    return $row ? easyBlogPostFromRow($row) : null;
}

function easyBlogAdminAccount(PDO $pdo): array
{
    $row = $pdo->query('SELECT id, username, short_name, password_hash, password_updated_at FROM administrators ORDER BY id LIMIT 1')->fetch();
    if (!$row) throw new RuntimeException('Administrator is missing.');
    return ['id' => $row['id'], 'username' => $row['username'], 'shortName' => $row['short_name'], 'passwordHash' => $row['password_hash'], 'passwordUpdatedAt' => easyBlogDatabaseDate($row['password_updated_at'])];
}

function easyBlogAdminPosts(PDO $pdo): array
{
    $statement = $pdo->query(easyBlogPostSelect() . ' ORDER BY p.published_at DESC');
    return array_map('easyBlogPostFromRow', $statement->fetchAll());
}

function easyBlogSavePost(PDO $pdo, array $request): array
{
    $post = $request['post'] ?? null;
    $revision = $request['revision'] ?? '';
    if (!is_array($post) || !is_string($revision)) throw new InvalidArgumentException('Invalid post data.');
    validateDocument('easy-blog', ['posts' => [$post]]);
    if ($post['expDate'] !== null && new DateTimeImmutable($post['expDate']) <= new DateTimeImmutable($post['date'])) {
        throw new InvalidArgumentException('Expiration must be later than publication.');
    }
    $pdo->beginTransaction();
    try {
        $existing = null;
        if (($request['isNew'] ?? false) !== true) {
            $statement = $pdo->prepare('SELECT author_id, updated_at FROM posts WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $post['id']]);
            $existing = $statement->fetch();
            if (!$existing) throw new InvalidArgumentException('The post no longer exists.');
            if (!hash_equals((string) easyBlogDatabaseDate($existing['updated_at']), $revision)) {
                throw new InvalidArgumentException('This post changed since the form was opened. Copy your edits, reload, and try again.');
            }
            $post['authorId'] = $existing['author_id'];
        }
        $conflict = $pdo->prepare('SELECT id FROM posts WHERE permalink = :permalink AND id <> :id');
        $conflict->execute(['permalink' => $post['permaLink'], 'id' => $post['id']]);
        if ($conflict->fetchColumn() !== false) throw new InvalidArgumentException('That permalink is already used by another post.');

        $categoryId = null;
        if ($post['catName'] !== '') {
            $category = $pdo->prepare('SELECT id, description FROM categories WHERE name = :name FOR UPDATE');
            $category->execute(['name' => $post['catName']]);
            $categoryRow = $category->fetch();
            if ($categoryRow) {
                $categoryId = $categoryRow['id'];
            } else {
                $insert = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
                $insert->execute(['name' => $post['catName'], 'description' => $post['catDesc']]);
                $categoryId = $pdo->lastInsertId();
            }
        }
        $values = [
            'id' => $post['id'], 'author_id' => $post['authorId'], 'category_id' => $categoryId,
            'status' => $post['status'], 'published_at' => easyBlogUtcDate($post['date']), 'expires_at' => easyBlogUtcDate($post['expDate']),
            'title' => $post['title'], 'description' => $post['description'], 'content' => $post['content'],
            'cover_photo_src' => $post['coverPhoto']['src'] ?? null, 'cover_photo_alt' => $post['coverPhoto']['alt'] ?? null,
            'cover_photo_caption' => $post['coverPhoto']['caption'] ?? null, 'blog_photo_src' => $post['blogPhoto']['src'] ?? null,
            'blog_photo_alt' => $post['blogPhoto']['alt'] ?? null, 'blog_photo_caption' => $post['blogPhoto']['caption'] ?? null,
            'permalink' => $post['permaLink'], 'updated_at' => easyBlogUtcDate($post['updatedAt']),
        ];
        if ($existing) {
            $sql = 'UPDATE posts SET author_id=:author_id, category_id=:category_id, status=:status, published_at=:published_at, expires_at=:expires_at, title=:title, description=:description, content=:content, cover_photo_src=:cover_photo_src, cover_photo_alt=:cover_photo_alt, cover_photo_caption=:cover_photo_caption, blog_photo_src=:blog_photo_src, blog_photo_alt=:blog_photo_alt, blog_photo_caption=:blog_photo_caption, permalink=:permalink, updated_at=:updated_at WHERE id=:id';
        } else {
            $sql = 'INSERT INTO posts (id, author_id, category_id, status, published_at, expires_at, title, description, content, cover_photo_src, cover_photo_alt, cover_photo_caption, blog_photo_src, blog_photo_alt, blog_photo_caption, permalink, updated_at) VALUES (:id,:author_id,:category_id,:status,:published_at,:expires_at,:title,:description,:content,:cover_photo_src,:cover_photo_alt,:cover_photo_caption,:blog_photo_src,:blog_photo_alt,:blog_photo_caption,:permalink,:updated_at)';
        }
        $pdo->prepare($sql)->execute($values);
        $pdo->commit();
        return ['id' => $post['id']];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function easyBlogUpdateAdminPassword(PDO $pdo, array $request): array
{
    $hash = $request['passwordHash'] ?? null;
    if (!is_string($hash) || $hash === '') throw new InvalidArgumentException('Invalid password hash.');
    $statement = $pdo->prepare('UPDATE administrators SET password_hash = :hash, password_updated_at = UTC_TIMESTAMP(6)');
    $statement->execute(['hash' => $hash]);
    if ($statement->rowCount() < 1) throw new RuntimeException('Administrator is missing.');
    return ['updated' => true];
}
