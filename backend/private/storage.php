<?php
declare(strict_types=1);
require_once __DIR__ . '/validation.php';

// All writers lock the stable .lock file, then atomically replace the JSON file.
function updateDocument(string $name, callable $change): void
{
    if (!in_array($name, ['easy-blog', 'easy-admin'], true)) throw new RuntimeException('Unknown document.');
    $path = __DIR__ . '/data/' . $name . '.json';
    $lock = fopen($path . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Unable to lock data.');
    $temp = null;
    try {
        $raw = file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Unable to read data.');
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $next = $change($data, hash('sha256', $raw));
        validateDocument($name, $next);
        $encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $temp = tempnam(dirname($path), '.save-');
        if ($temp === false || file_put_contents($temp, $encoded) !== strlen($encoded)) throw new RuntimeException('Unable to write data.');
        if (!chmod($temp, fileperms($path) & 0777)) throw new RuntimeException('Unable to preserve file permissions.');
        if (!rename($temp, $path)) throw new RuntimeException('Unable to replace data.');
        $temp = null;
    } finally {
        if ($temp !== null && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
