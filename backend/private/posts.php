<?php
declare(strict_types=1);

function publishedPosts(array $posts, DateTimeImmutable $now): array
{
    $visible = array_filter($posts, static function ($post) use ($now): bool {
        if (!is_array($post) || ($post['status'] ?? null) !== 'published') {
            return false;
        }
        foreach (['date', 'expDate'] as $field) {
            if (!array_key_exists($field, $post)) {
                return false;
            }
            $value = $post[$field];
            if ($field === 'expDate' && $value === null) {
                continue;
            }
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
                return false;
            }
            try {
                $time = new DateTimeImmutable($value);
                $errors = DateTimeImmutable::getLastErrors();
                if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                    return false;
                }
            } catch (Exception) {
                return false;
            }
            if (($field === 'date' && $time > $now) || ($field === 'expDate' && $time <= $now)) {
                return false;
            }
        }
        return true;
    });
    usort($visible, static fn (array $a, array $b): int =>
        new DateTimeImmutable($b['date']) <=> new DateTimeImmutable($a['date']));
    // Explicit public fields prevent future internal metadata leaking into responses.
    $fields = array_flip(['id', 'authorId', 'date', 'title', 'description', 'content',
        'coverPhoto', 'blogPhoto', 'permaLink', 'expDate', 'catName', 'catDesc', 'updatedAt']);
    return array_map(static fn (array $post): array => array_intersect_key($post, $fields), $visible);
}
