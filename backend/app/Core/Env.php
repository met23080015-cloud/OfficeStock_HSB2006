<?php
declare(strict_types=1);

final class Env
{
    private static array $variables = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            [$key, $value] = explode('=', $line, 2) + [1 => ''];
            self::$variables[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Ưu tiên đọc biến môi trường hệ thống của Render
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        return self::$variables[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $val = self::get($key);
        return $val !== null ? (int)$val : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $val = strtolower(self::get($key) ?? '');
        if (in_array($val, ['1', 'true', 'yes', 'on'], true)) return true;
        if (in_array($val, ['0', 'false', 'no', 'off'], true)) return false;
        return $default;
    }
}
