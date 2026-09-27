<?php
declare(strict_types=1);

require_once __DIR__ . '/validation.php';

function field(string $name, string $default = ''): string
{
    $value = $_POST[$name] ?? $default;
    if (!is_string($value)) throw new InvalidArgumentException('Invalid form field: ' . $name);
    return $value;
}

function formDate(string $value, string $timezone, bool $optional = false): ?string
{
    if ($value === '' && $optional) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone($timezone));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i') !== $value) {
        throw new InvalidArgumentException('Enter a valid publication or expiration date.');
    }
    return $date->format(DATE_ATOM);
}

function editDate(?string $value, string $timezone): string
{
    return $value === null ? '' : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i');
}

function photoFromForm(string $name): ?array
{
    $src = trim(field($name . '_src'));
    if ($src === '') return null;
    if (!preg_match('~^/(?!/)[^\s<>]+$~D', $src) && !(filter_var($src, FILTER_VALIDATE_URL) && str_starts_with($src, 'https://'))) {
        throw new InvalidArgumentException('Image paths must start with / or use an HTTPS URL.');
    }
    return ['src' => $src, 'alt' => trim(field($name . '_alt')), 'caption' => trim(field($name . '_caption'))];
}

function postFromForm(array $account): array
{
    $id = field('id');
    $slug = trim(field('permaLink'));
    if ($slug === '') $slug = trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', field('title'))), '-');
    $post = [
        'id' => $id ?: 'post-' . bin2hex(random_bytes(12)), 'authorId' => $account['admin']['id'],
        'status' => field('status'), 'date' => formDate(field('date'), $account['site']['timeZone']),
        'title' => trim(field('title')), 'description' => trim(field('description')), 'content' => field('content'),
        'coverPhoto' => photoFromForm('coverPhoto'), 'blogPhoto' => photoFromForm('blogPhoto'),
        'permaLink' => $slug, 'expDate' => formDate(field('expDate'), $account['site']['timeZone'], true),
        'catName' => trim(field('catName')), 'catDesc' => trim(field('catDesc')),
        'updatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
    validateDocument('easy-blog', ['posts' => [$post]]);
    return $post;
}
