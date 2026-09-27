<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/backend/private/helpers.php';

fwrite(STDOUT, "New administrator password: ");
$settings = shell_exec('stty -g');
shell_exec('stty -echo');
$password = rtrim((string) fgets(STDIN), "\r\n");
if ($settings !== null) shell_exec('stty ' . escapeshellarg(trim($settings)));
fwrite(STDOUT, "\nConfirm password: ");
shell_exec('stty -echo');
$confirm = rtrim((string) fgets(STDIN), "\r\n");
if ($settings !== null) shell_exec('stty ' . escapeshellarg(trim($settings)));
fwrite(STDOUT, "\n");

if ($password !== $confirm) { fwrite(STDERR, "Passwords do not match.\n"); exit(1); }
if (strlen($password) < 12) { fwrite(STDERR, "Use at least 12 characters.\n"); exit(1); }
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) throw new RuntimeException('Unable to hash password.');
$result = dataRequest(['request' => 'update-admin-password', 'passwordHash' => $hash]);
if (($result['updated'] ?? false) !== true) throw new RuntimeException('Administrator password was not updated.');
fwrite(STDOUT, "Administrator password updated.\n");
