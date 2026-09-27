<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'service' => 'Easy Blog API'
], JSON_THROW_ON_ERROR);
