<?php
declare(strict_types=1);

final class Request
{
    private static ?array $json = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir)) ?: '/';
        }
        return '/' . ltrim($uri, '/');
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function json(): array
    {
        if (self::$json !== null) return self::$json;
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return self::$json = [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Response::error('Invalid JSON request body.', 400, 'INVALID_JSON');
        }
        return self::$json = $decoded;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $data = self::json();
        return $data[$key] ?? $default;
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
