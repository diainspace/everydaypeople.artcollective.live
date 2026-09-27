<?php
declare(strict_types=1);

// Implements the keywords used by this project's two schemas, not a general
// JSON Schema engine. Reject unsupported validation keywords when schemas evolve.
function schemaErrors(mixed $value, array $schema, array $root, string $path = 'data'): array
{
    $supported = ['$schema', '$defs', '$ref', 'title', 'description', 'default', 'type', 'required', 'properties', 'additionalProperties', 'items', 'oneOf', 'enum', 'minLength', 'minimum', 'pattern', 'format'];
    foreach (array_keys($schema) as $key) {
        if (!in_array($key, $supported, true)) throw new RuntimeException('Unsupported schema keyword: ' . $key);
    }
    if (isset($schema['$ref'])) {
        $target = $root;
        if (!str_starts_with($schema['$ref'], '#/')) throw new RuntimeException('Unsupported schema reference.');
        foreach (explode('/', substr($schema['$ref'], 2)) as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            $target = $target[$part] ?? throw new RuntimeException('Invalid schema reference.');
        }
        return schemaErrors($value, $target, $root, $path);
    }
    if (isset($schema['oneOf'])) {
        $matches = 0;
        foreach ($schema['oneOf'] as $option) if (!schemaErrors($value, $option, $root, $path)) $matches++;
        return $matches === 1 ? [] : ["$path has an invalid structure."];
    }
    $matches = false;
    foreach ((array) ($schema['type'] ?? []) as $type) {
        $matches = $matches || match ($type) {
            'object' => $value instanceof stdClass,
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'null' => $value === null,
            default => throw new RuntimeException('Unsupported schema type.'),
        };
    }
    if (isset($schema['type']) && !$matches) return ["$path has the wrong value type."];
    $errors = [];
    if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) $errors[] = "$path is not an allowed value.";
    if (is_string($value)) {
        if (isset($schema['minLength']) && preg_match_all('/./us', $value) < $schema['minLength']) $errors[] = "$path is required.";
        if (isset($schema['pattern']) && preg_match('~' . str_replace('~', '\\~', $schema['pattern']) . '~uD', $value) !== 1) $errors[] = "$path has an invalid format.";
        if (isset($schema['format'])) {
            if ($schema['format'] !== 'date-time') throw new RuntimeException('Unsupported schema format.');
            $valid = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $value) === 1;
            try {
                new DateTimeImmutable($value);
                $issues = DateTimeImmutable::getLastErrors();
                $valid = $valid && ($issues === false || (!$issues['warning_count'] && !$issues['error_count']));
            } catch (Exception) { $valid = false; }
            if (!$valid) $errors[] = "$path must be a valid date and time with a timezone.";
        }
    }
    if (is_int($value) && isset($schema['minimum']) && $value < $schema['minimum']) $errors[] = "$path is too small.";
    if ($value instanceof stdClass) {
        foreach ($schema['required'] ?? [] as $key) if (!property_exists($value, $key)) $errors[] = "$path.$key is required.";
        foreach (get_object_vars($value) as $key => $item) {
            if (isset($schema['properties'][$key])) $errors = array_merge($errors, schemaErrors($item, $schema['properties'][$key], $root, "$path.$key"));
            elseif (($schema['additionalProperties'] ?? true) === false) $errors[] = "$path.$key is not supported.";
        }
    }
    if (is_array($value) && isset($schema['items'])) foreach ($value as $key => $item) $errors = array_merge($errors, schemaErrors($item, $schema['items'], $root, "$path.$key"));
    return $errors;
}

function validateDocument(string $name, array $document): void
{
    if (!in_array($name, ['easy-blog', 'easy-admin'], true)) throw new RuntimeException('Unknown document.');
    $schema = json_decode(file_get_contents(__DIR__ . '/schemas/' . $name . '.schema.json'), true, 512, JSON_THROW_ON_ERROR);
    $value = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    $errors = schemaErrors($value, $schema, $schema);
    if ($errors) throw new InvalidArgumentException(implode(' ', $errors));
}
