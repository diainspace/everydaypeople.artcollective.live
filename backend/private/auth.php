<?php
declare(strict_types=1);

function startAdminSession(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('easy_blog_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/admin/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) throw new RuntimeException('Unable to start session.');
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function adminLoggedIn(array $admin): bool
{
    $ok = ($_SESSION['admin_id'] ?? null) === $admin['id']
        && hash_equals(hash('sha256', $admin['passwordHash']), $_SESSION['credential'] ?? '')
        && time() - ($_SESSION['last_seen'] ?? 0) < 1800;
    if ($ok) $_SESSION['last_seen'] = time();
    else unset($_SESSION['admin_id'], $_SESSION['credential'], $_SESSION['last_seen']);
    return $ok;
}

function checkCsrf(): void
{
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        throw new InvalidArgumentException('This form has expired. Reload the page and try again.');
    }
}

function authenticate(array $admin, string $username, string $password): bool
{
    // Shared single-account limiter persists across sessions and IP changes.
    $file = fopen(__DIR__ . '/data/login-attempts.json', 'c+');
    if (!$file || !flock($file, LOCK_EX)) throw new RuntimeException('Unable to check sign-in attempts.');
    try {
        $raw = stream_get_contents($file);
        $state = $raw === '' ? ['since' => time(), 'count' => 0] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (time() - $state['since'] >= 300) $state = ['since' => time(), 'count' => 0];
        if ($state['count'] >= 5) throw new InvalidArgumentException('Too many sign-in attempts. Please wait five minutes and try again.');
        $verified = password_verify($password, $admin['passwordHash']);
        $ok = hash_equals($admin['username'], $username) && $verified;
        $state['count'] = $ok ? 0 : $state['count'] + 1;
        rewind($file); ftruncate($file, 0);
        if (fwrite($file, json_encode($state, JSON_THROW_ON_ERROR)) === false || !fflush($file)) throw new RuntimeException('Unable to record sign-in attempt.');
    } finally { flock($file, LOCK_UN); fclose($file); }
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION = ['admin_id' => $admin['id'], 'credential' => hash('sha256', $admin['passwordHash']), 'last_seen' => time(), 'csrf' => bin2hex(random_bytes(32))];
    }
    return $ok;
}
