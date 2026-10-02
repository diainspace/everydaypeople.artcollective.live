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
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 24 * 1024 * 1024) {
            throw new InvalidArgumentException('The combined form submission is larger than the 24 MB limit.');
        }
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

        if ($action === 'save-website-content') {
            dataRequest([
                'request' => 'save-website-content',
                'values' => websiteContentFromForm(),
                'revision' => field('revision'),
            ]);
            header('Location: /admin/?section=website&saved=1');
            exit;
        }

        if ($action === 'save-category') {
            $category = categoryFromForm();
            $saved = dataRequest([
                'request' => 'save-category',
                'category' => $category,
                'revision' => field('revision'),
                'isNew' => $category['id'] === '',
            ]);
            header('Location: /admin/?section=categories&edit_category=' . rawurlencode($saved['id']) . '&saved=1');
            exit;
        }

        if ($action === 'delete-category') {
            dataRequest([
                'request' => 'delete-category',
                'id' => field('category_id'),
                'revision' => field('revision'),
            ]);
            header('Location: /admin/?section=categories&deleted=1');
            exit;
        }

        throw new InvalidArgumentException('Unknown form action.');
    }

    $loggedIn = adminLoggedIn($admin);
    if (isset($_GET['saved'])) {
        $message = match ($_GET['section'] ?? '') {
            'website' => 'Website content saved.',
            'categories' => 'Category saved.',
            default => 'Post saved.',
        };
    }
    if (isset($_GET['deleted'])) $message = 'Category deleted. Its posts now have no category.';
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
$mainClass = 'admin-page';
$pageScript = '/assets/admin-categories.js';
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
    $section = isset($_GET['section']) && is_string($_GET['section']) ? $_GET['section'] : 'posts';
    if (!in_array($section, ['posts', 'website', 'categories'], true)) $section = 'posts';
    $editId = isset($_GET['edit']) && is_string($_GET['edit']) ? $_GET['edit'] : '';
    $editing = null;
    $allPosts = [];
    $revision = '';
    $websiteContent = ['revision' => '', 'items' => []];
    $categories = [];
    $editingCategory = null;
    $categoryEditId = isset($_GET['edit_category']) && is_string($_GET['edit_category']) ? $_GET['edit_category'] : '';
    try {
        if ($section === 'website') {
            $websiteContent = dataRequest(['request' => 'website-content']);
            $revision = $websiteContent['revision'];
            if ($error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $submittedContent = is_array($_POST['content'] ?? null) ? $_POST['content'] : [];
                foreach ($websiteContent['items'] as &$item) {
                    $submitted = $submittedContent[$item['key']] ?? null;
                    if (is_string($submitted)) $item['value'] = $submitted;
                }
                unset($item);
                $revision = field('revision');
            }
        } elseif ($section === 'categories') {
            $categories = dataRequest(['request' => 'categories']);
            foreach ($categories as $candidate) if ($candidate['id'] === $categoryEditId) $editingCategory = $candidate;
            if ($categoryEditId !== '' && $categoryEditId !== 'new' && $editingCategory === null) throw new InvalidArgumentException('Category not found.');
            $revision = $editingCategory['updatedAt'] ?? '';
            if ($error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') $revision = field('revision');
        } else {
        $allPosts = dataRequest(['request' => 'admin-posts']);
        $categories = dataRequest(['request' => 'categories']);
        foreach ($allPosts as $candidate) if ($candidate['id'] === $editId) $editing = $candidate;
        if ($editId !== '' && $editId !== 'new' && $editing === null) throw new InvalidArgumentException('Post not found.');
        $revision = $editing['updatedAt'] ?? '';
        if ($error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') $revision = field('revision');
        }
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
        $allPosts = [];
        $editing = null;
        $revision = '';
    }
    ?>

    <nav class="admin-actions" aria-label="Admin actions">
        <a href="/admin/"<?= $section === 'posts' && $editId !== 'new' ? ' aria-current="page"' : '' ?>>Blog posts</a>
        <a href="/admin/?edit=new"<?= $section === 'posts' && $editId === 'new' ? ' aria-current="page"' : '' ?>>New post</a>
        <a href="/admin/?section=categories"<?= $section === 'categories' ? ' aria-current="page"' : '' ?>>Manage categories</a>
        <a href="/admin/?section=website"<?= $section === 'website' ? ' aria-current="page"' : '' ?>>Website content</a>
    </nav>

    <?php if ($section === 'categories'): ?>
        <h2>Manage Categories</h2>
        <nav class="admin-actions button-actions" aria-label="Category actions">
            <a class="admin-button button-secondary" href="/admin/?section=categories">All Categories</a>
            <a class="admin-button" href="/admin/?section=categories&amp;edit_category=new">Add Category</a>
        </nav>
        <?php if ($categoryEditId === ''): ?>
            <?php if (!$categories): ?><p>No categories yet.</p><?php endif; ?>
            <div class="post-list">
                <?php foreach ($categories as $category): ?>
                    <article>
                        <h3><?= escape($category['name']) ?></h3>
                        <p><?= escape($category['description']) ?></p>
                        <div class="category-actions">
                            <a class="admin-button button-secondary" href="/admin/?section=categories&amp;edit_category=<?= rawurlencode($category['id']) ?>">Edit</a>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
                                <input type="hidden" name="action" value="delete-category">
                                <input type="hidden" name="category_id" value="<?= escape($category['id']) ?>">
                                <input type="hidden" name="revision" value="<?= escape($category['updatedAt']) ?>">
                                <button type="submit" class="admin-button button-danger" data-confirm="Delete this category? Its posts will be changed to No category.">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php
            $category = $editingCategory ?? ['id' => '', 'name' => '', 'description' => ''];
            if ($error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $category = categoryFromForm();
            }
            ?>
            <h3><?= $editingCategory ? 'Edit category' : 'Add category' ?></h3>
            <form method="post" class="admin-form">
                <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="save-category">
                <input type="hidden" name="category_id" value="<?= escape($category['id']) ?>">
                <input type="hidden" name="revision" value="<?= escape($revision) ?>">
                <label>Category Name <input name="category_name" required maxlength="150" value="<?= escape($category['name']) ?>"></label>
                <label>Category Description <textarea name="category_description" rows="6"><?= escape($category['description']) ?></textarea></label>
                <div class="form-actions">
                    <a class="admin-button button-muted" href="/admin/?section=categories">Cancel</a>
                    <button type="submit">Save Category</button>
                </div>
            </form>
        <?php endif; ?>
    <?php elseif ($section === 'website'): ?>
        <h2>Website Content</h2>
        <p>Edit the words and links used throughout the public website.</p>
        <?php
        $websiteItems = [];
        foreach ($websiteContent['items'] as $item) $websiteItems[$item['key']] = $item;
        $contentGroups = [
            'Homepage — Main Content' => ['home_main_statement'],
            'Homepage — Archive Promotion' => ['promote_archive', 'home_supporting_text', 'home_archive_link_text'],
            'Homepage — Alternate Promotion' => ['alternate_promotion_text', 'alternate_show_button', 'alternate_button_text', 'alternate_button_url'],
            'Homepage — About' => ['home_about_label', 'home_belief_statement'],
            'Stories Page' => ['stories_page_title'],
            'About Page' => ['about_title', 'about_body'],
            'Footer' => ['footer_collective_name', 'footer_collective_link', 'footer_supporting_text', 'footer_closing_statement', 'footer_copyright_statement'],
        ];
        ?>
        <form method="post" class="admin-form editor-form">
            <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="save-website-content">
            <input type="hidden" name="revision" value="<?= escape($revision) ?>">
            <?php foreach ($contentGroups as $groupTitle => $keys): ?>
                <fieldset class="content-group">
                    <legend><?= escape($groupTitle) ?></legend>
                    <?php foreach ($keys as $key): $item = $websiteItems[$key] ?? null; if ($item === null) continue; ?>
                    <?php if ($item['type'] === 'toggle'): ?>
                    <label class="toggle-field">
                        <input type="hidden" name="content[<?= escape($item['key']) ?>]" value="0">
                        <input type="checkbox" name="content[<?= escape($item['key']) ?>]" value="1" <?= $item['value'] === '1' ? 'checked' : '' ?>>
                        <span><?= escape($item['label']) ?></span>
                    </label>
                    <?php else: ?>
                    <label><?= escape($item['label']) ?>
                        <?php if ($item['type'] === 'text'): ?>
                            <input name="content[<?= escape($item['key']) ?>]" value="<?= escape($item['value']) ?>">
                        <?php else: ?>
                            <textarea name="content[<?= escape($item['key']) ?>]" rows="<?= $item['type'] === 'markdown' ? '12' : '4' ?>"><?= escape($item['value']) ?></textarea>
                            <?php if ($item['type'] === 'markdown'): ?><small>Supports Markdown headings and paragraphs.</small><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($item['key'] === 'footer_copyright_statement'): ?><small>The site adds “© <?= date('Y') ?>” automatically.</small><?php endif; ?>
                    </label>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
            <button type="submit">Save website content</button>
        </form>
    <?php elseif ($editId === ''): ?>
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
            foreach (['id', 'status', 'title', 'description', 'content', 'permaLink', 'catName'] as $name) $post[$name] = field($name, $post[$name]);
            foreach (['date', 'expDate'] as $name) $post[$name] = field($name);
            foreach (['coverPhoto', 'blogPhoto'] as $name) {
                $post[$name] = field($name . '_src') === '' ? null : ['src' => field($name . '_src'), 'alt' => field($name . '_alt'), 'caption' => field($name . '_caption')];
            }
        }
        $dateValue = str_contains($post['date'], 'T') && str_contains($post['date'], ':') ? editDate($post['date'], $site['timeZone']) : $post['date'];
        $expValue = $post['expDate'] === null || $post['expDate'] === '' ? '' : (str_contains($post['expDate'], 'T') ? editDate($post['expDate'], $site['timeZone']) : $post['expDate']);
        $selectedCategoryDescription = '';
        foreach ($categories as $categoryOption) {
            if ($categoryOption['name'] === $post['catName']) $selectedCategoryDescription = $categoryOption['description'];
        }
        ?>
        <h2><?= $editing ? 'Edit post' : 'New post' ?></h2>
        <form method="post" enctype="multipart/form-data" class="admin-form editor-form">
            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
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
            <fieldset class="photo-editor">
                <?php $storyPhotoPath = $post['coverPhoto']['src'] ?? ''; ?>
                <legend>Story Photo</legend>
                <img class="admin-image-preview" data-upload-preview="coverPhoto_upload"<?= ($post['coverPhoto']['src'] ?? '') !== '' ? ' src="' . escape($post['coverPhoto']['src']) . '"' : ' hidden' ?> alt="">
                <label>Upload a new Story Photo <input name="coverPhoto_upload" type="file" accept="image/jpeg,image/png,image/webp"<?= $storyPhotoPath !== '' ? ' disabled' : '' ?>></label>
                <small>JPEG, PNG, or WebP up to 10 MB. Saved as a 1600 × 1200 WebP. A new upload updates this story without deleting the previous file.</small>
                <label>Current path or HTTPS URL <input name="coverPhoto_src" data-photo-path="coverPhoto_upload"<?= $storyPhotoPath !== '' ? ' readonly' : '' ?> value="<?= escape($storyPhotoPath) ?>"></label>
                <button type="button" class="photo-clear" data-photo-clear="coverPhoto_upload"<?= $storyPhotoPath === '' ? ' hidden' : '' ?>>Clear current photo</button>
                <label>Alternative text <input name="coverPhoto_alt" data-upload-alt="coverPhoto_upload"<?= ($post['coverPhoto']['src'] ?? '') !== '' ? ' required' : '' ?> value="<?= escape($post['coverPhoto']['alt'] ?? '') ?>"></label>
                <label>Caption <input name="coverPhoto_caption" value="<?= escape($post['coverPhoto']['caption'] ?? '') ?>"></label>
            </fieldset>
            <fieldset class="photo-editor">
                <?php $moreStoriesPhotoPath = $post['blogPhoto']['src'] ?? ''; ?>
                <legend>More Stories Photo</legend>
                <img class="admin-image-preview admin-image-preview-square" data-upload-preview="blogPhoto_upload"<?= ($post['blogPhoto']['src'] ?? '') !== '' ? ' src="' . escape($post['blogPhoto']['src']) . '"' : ' hidden' ?> alt="">
                <label>Upload a new More Stories Photo <input name="blogPhoto_upload" type="file" accept="image/jpeg,image/png,image/webp"<?= $moreStoriesPhotoPath !== '' ? ' disabled' : '' ?>></label>
                <small>JPEG, PNG, or WebP up to 10 MB. Saved as a 1400 × 1400 WebP. A new upload updates this story without deleting the previous file.</small>
                <label>Current path or HTTPS URL <input name="blogPhoto_src" data-photo-path="blogPhoto_upload"<?= $moreStoriesPhotoPath !== '' ? ' readonly' : '' ?> value="<?= escape($moreStoriesPhotoPath) ?>"></label>
                <button type="button" class="photo-clear" data-photo-clear="blogPhoto_upload"<?= $moreStoriesPhotoPath === '' ? ' hidden' : '' ?>>Clear current photo</button>
                <label>Alternative text <input name="blogPhoto_alt" data-upload-alt="blogPhoto_upload"<?= ($post['blogPhoto']['src'] ?? '') !== '' ? ' required' : '' ?> value="<?= escape($post['blogPhoto']['alt'] ?? '') ?>"></label>
                <label>Caption <input name="blogPhoto_caption" value="<?= escape($post['blogPhoto']['caption'] ?? '') ?>"></label>
            </fieldset>
            <label>Category Name
                <input name="catName" list="category-options" value="<?= escape($post['catName']) ?>" autocomplete="off">
                <small>Choose a category created under Manage Categories, or leave this blank.</small>
            </label>
            <datalist id="category-options">
                <?php foreach ($categories as $categoryOption): ?><option value="<?= escape($categoryOption['name']) ?>" data-description="<?= escape($categoryOption['description']) ?>"></option><?php endforeach; ?>
            </datalist>
            <div class="category-description-display">
                <strong>Category Description</strong>
                <p id="selected-category-description"><?= $selectedCategoryDescription === '' ? 'No category selected.' : escape($selectedCategoryDescription) ?></p>
                <a href="/admin/?section=categories">Manage categories</a>
            </div>
            <button type="submit">Save post</button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
