<?php
declare(strict_types=1);

final class Cors
{
    public static function apply(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = array_values(array_filter(array_map(
            'trim',
            explode(',', (string)Env::get('CORS_ALLOWED_ORIGINS', ''))
        )));

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
            header('Access-Control-Max-Age: 600');
        }

        if (Request::method() === 'OPTIONS') {
            if ($origin !== '' && !in_array($origin, $allowed, true)) {
                Response::error('Origin is not allowed.', 403, 'CORS_DENIED');
            }
            http_response_code(204);
            exit;
        }
    }
}
