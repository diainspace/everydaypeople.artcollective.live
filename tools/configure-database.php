<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

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

try {
    $config = [
        'host' => prompt('Database host', 'localhost'),
        'port' => prompt('Database port', '3306'),
        'name' => prompt('Database name', 'acl_easyblog'),
        'user' => prompt('Database user', 'acl_easyblog_app'),
        'password' => secretPrompt('Database password'),
    ];

    if (!ctype_digit($config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535) {
        throw new InvalidArgumentException('Invalid database port.');
    }

    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    $version = $pdo->query("SELECT version FROM schema_migrations WHERE version = '001_initial_schema'")->fetchColumn();
    if ($version !== '001_initial_schema') throw new RuntimeException('Migration 001_initial_schema is missing.');
    $postCount = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();

    $directory = dirname(__DIR__) . '/backend/private/config';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the private configuration directory.');
    }
    $path = $directory . '/database.php';
    if (is_file($path)) throw new RuntimeException('Database configuration already exists.');
    $content = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($path, $content, LOCK_EX) !== strlen($content) || !chmod($path, 0600)) {
        throw new RuntimeException('Unable to save database configuration.');
    }

    fwrite(STDOUT, "Database connection verified. Found {$postCount} post" . ($postCount === 1 ? '' : 's') . ".\n");
    fwrite(STDOUT, "Private database configuration created.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Configuration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
