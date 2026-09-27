<?php
declare(strict_types=1);

require_once __DIR__ . '/data/easy-blog-data.php';

function escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dataRequest(array $request): mixed
{
    $response = json_decode(easyBlogData($request), true, 512, JSON_THROW_ON_ERROR);
    if (($response['ok'] ?? false) !== true) throw new InvalidArgumentException($response['error'] ?? 'The data request could not be completed.');
    return $response['data'];
}

function postUrl(array $post): string
{
    return '/archive/' . rawurlencode($post['permaLink']);
}

function displayDate(string $date, string $timezone): string
{
    return (new DateTimeImmutable($date))->setTimezone(new DateTimeZone($timezone))->format('F j, Y');
}
