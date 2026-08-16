<?php
declare(strict_types=1);

function next_code(string $prefix): string
{
    return strtoupper($prefix) . '-' . gmdate('Ymd-His') . '-' . random_int(100, 999);
}

function int_or_null(mixed $value): ?int
{
    if ($value === null || $value === '' || (int)$value <= 0) return null;
    return (int)$value;
}

function safe_sort(string $requested, array $allowList, string $default): string
{
    return $allowList[$requested] ?? $allowList[$default];
}

function like_term(string $value): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value) . '%';
}
