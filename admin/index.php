<?php
declare(strict_types=1);

require dirname(__DIR__) . '/backend/private/helpers.php';
require dirname(__DIR__) . '/backend/private/auth.php';
require dirname(__DIR__) . '/backend/private/editor.php';

header('X-Robots-Tag: noindex, nofollow');

try {
    startAdminSession();
    $site = dataRequest(['request' => 'site-settings']);
    $admin = dataRequest(['request' => 'admin-account']);
    $account = ['site' => $site, 'admin' => $admin];
    $message = null;
    $error = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        checkCsrf();
        $action = field('action');

        if ($action === 'login') {
            if (!authenticate($admin, trim(field('username')), field('password'))) {
                throw new InvalidArgumentException('The username or password is incorrect.');
            }
            header('Location: /admin/');
            exit;
        }

        if (!adminLoggedIn($admin)) {
            throw new InvalidArgumentException('Your session expired. Please sign in again.');
        }

        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: /admin/');
            exit;
        }

        if ($action === 'save') {
            $postToSave = postFromForm($account);
            $saved = dataRequest([
                'request' => 'save-post', 'post' => $postToSave,
                'revision' => field('revision'), 'isNew' => field('id') === '',
            ]);
            $savedId = $saved['id'];
            header('Location: /admin/?edit=' . rawurlencode($savedId) . '&saved=1');
            exit;
        }

        throw new InvalidArgumentException('Unknown form action.');
    }

    $loggedIn = adminLoggedIn($admin);
    if (isset($_GET['saved'])) $message = 'Post saved.';
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
    $loggedIn = isset($admin) && adminLoggedIn($admin);
} catch (Throwable $exception) {
    error_log('Easy Blog: ' . $exception->getMessage());
    http_response_code(500);
    $site = ['title' => 'Easy Blog', 'description' => ''];
    $pageTitle = 'Temporarily unavailable';
    require dirname(__DIR__) . '/includes/header.php';
    ?><h1>Temporarily unavailable</h1><p>Please try again later.</p><?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$pageTitle = 'Easy Admin';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Easy Admin</h1>

<?php if (!$loggedIn): ?>
    <?php if ($error): ?><p class="form-error" role="alert"><?= escape($error) ?></p><?php endif; ?>
    <?php if ($admin['passwordHash'] === 'PLACEHOLDER_GENERATE_WITH_PHP_DURING_SETUP'): ?>
        <p class="form-note">The administrator password has not been set yet. Run the local password setup command first.</p>
    <?php endif; ?>
    <form method="post" class="admin-form" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="login">
        <label>Username
            <input name="username" type="text" required autocomplete="username" autofocus>
        </label>
        <label>Password
            <input name="password" type="password" required autocomplete="current-password">
        </label>
        <button type="submit">Sign in</button>
    </form>
<?php else: ?>
    <div class="admin-toolbar">
        <p>Signed in as <?= escape($admin['shortName']) ?>.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="button-secondary">Sign out</button>
        </form>
    </div>

    <?php if ($message): ?><p class="form-success" role="status"><?= escape($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="form-error" role="alert"><?= escape($error) ?></p><?php endif; ?>

    <?php
    try {
        $allPosts = dataRequest(['request' => 'admin-posts']);
        $editId = isset($_GET['edit']) && is_string($_GET['edit']) ? $_GET['edit'] : '';
        $editing = null;
        foreach ($allPosts as $candidate) if ($candidate['id'] === $editId) $editing = $candidate;
        if ($editId !== '' && $editId !== 'new' && $editing === null) throw new InvalidArgumentException('Post not found.');
        $revision = $editing['updatedAt'] ?? '';
        if ($error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') $revision = field('revision');
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
        $allPosts = [];
        $editing = null;
        $revision = '';
    }
    ?>

    <nav class="admin-actions" aria-label="Admin actions">
        <a href="/admin/">All posts</a>
        <a href="/admin/?edit=new">New post</a>
    </nav>

    <?php if ($editId === ''): ?>
        <h2>Posts</h2>
        <?php if (!$allPosts): ?><p>No posts yet.</p><?php endif; ?>
        <div class="post-list">
            <?php foreach ($allPosts as $post): ?>
                <article>
                    <p class="meta"><?= escape(ucfirst($post['status'])) ?> · <?= escape((new DateTimeImmutable($post['date']))->setTimezone(new DateTimeZone($site['timeZone']))->format('F j, Y')) ?></p>
                    <h3><?= escape($post['title']) ?></h3>
                    <p><a href="/admin/?edit=<?= rawurlencode($post['id']) ?>">Edit</a> · <a href="<?= escape(postUrl($post)) ?>">View public URL</a></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?php
        $post = $editing ?? [
            'id' => '', 'status' => 'draft',
            'date' => (new DateTimeImmutable('now', new DateTimeZone($site['timeZone'])))->format(DATE_ATOM),
            'title' => '', 'description' => '', 'content' => '',
            'coverPhoto' => null, 'blogPhoto' => null, 'permaLink' => '', 'expDate' => null,
            'catName' => '', 'catDesc' => '',
        ];
        if ($error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            foreach (['id', 'status', 'title', 'description', 'content', 'permaLink', 'catName', 'catDesc'] as $name) $post[$name] = field($name, $post[$name]);
            foreach (['date', 'expDate'] as $name) $post[$name] = field($name);
            foreach (['coverPhoto', 'blogPhoto'] as $name) {
                $post[$name] = field($name . '_src') === '' ? null : ['src' => field($name . '_src'), 'alt' => field($name . '_alt'), 'caption' => field($name . '_caption')];
            }
        }
        $dateValue = str_contains($post['date'], 'T') && str_contains($post['date'], ':') ? editDate($post['date'], $site['timeZone']) : $post['date'];
        $expValue = $post['expDate'] === null || $post['expDate'] === '' ? '' : (str_contains($post['expDate'], 'T') ? editDate($post['expDate'], $site['timeZone']) : $post['expDate']);
        ?>
        <h2><?= $editing ? 'Edit post' : 'New post' ?></h2>
        <form method="post" class="admin-form editor-form">
            <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escape($post['id']) ?>">
            <input type="hidden" name="revision" value="<?= escape($revision) ?>">

            <label>Title <input name="title" required value="<?= escape($post['title']) ?>"></label>
            <label>Permalink <input name="permaLink" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= escape($post['permaLink']) ?>"><small>Leave blank to create it from the title.</small></label>
            <label>Status
                <select name="status">
                    <?php foreach (['draft', 'published', 'archived'] as $status): ?><option value="<?= $status ?>" <?= $post['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Publication date <input name="date" type="datetime-local" required value="<?= escape($dateValue) ?>"></label>
            <label>Expiration date <input name="expDate" type="datetime-local" value="<?= escape($expValue) ?>"><small>Optional. The post disappears at this time.</small></label>
            <label>Description <textarea name="description" rows="3"><?= escape($post['description']) ?></textarea></label>
            <label>Content <textarea name="content" rows="14"><?= escape($post['content']) ?></textarea><small>Supports Markdown headings and paragraphs.</small></label>
            <fieldset>
                <legend>Cover photo</legend>
                <label>Path or HTTPS URL <input name="coverPhoto_src" value="<?= escape($post['coverPhoto']['src'] ?? '') ?>"></label>
                <label>Alternative text <input name="coverPhoto_alt" value="<?= escape($post['coverPhoto']['alt'] ?? '') ?>"></label>
                <label>Caption <input name="coverPhoto_caption" value="<?= escape($post['coverPhoto']['caption'] ?? '') ?>"></label>
            </fieldset>
            <fieldset>
                <legend>Blog photo</legend>
                <label>Path or HTTPS URL <input name="blogPhoto_src" value="<?= escape($post['blogPhoto']['src'] ?? '') ?>"></label>
                <label>Alternative text <input name="blogPhoto_alt" value="<?= escape($post['blogPhoto']['alt'] ?? '') ?>"></label>
                <label>Caption <input name="blogPhoto_caption" value="<?= escape($post['blogPhoto']['caption'] ?? '') ?>"></label>
            </fieldset>
            <label>Category name <input name="catName" value="<?= escape($post['catName']) ?>"></label>
            <label>Category description <textarea name="catDesc" rows="3"><?= escape($post['catDesc']) ?></textarea><small>An existing category automatically reuses its saved description.</small></label>
            <button type="submit">Save post</button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
