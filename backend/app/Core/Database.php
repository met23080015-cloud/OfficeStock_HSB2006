<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $host = Env::get('DB_HOST');
        $port = Env::int('DB_PORT', 3306);
        $name = Env::get('DB_NAME');
        $user = Env::get('DB_USER');
        $pass = Env::get('DB_PASSWORD', Env::get('DB_PASS', ''));

        if (!$host || !$name || !$user) {
            throw new RuntimeException('Database environment variables are incomplete.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        self::$pdo = new PDO($dsn, $user, (string)$pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            // ⬇️ THÊM 2 DÒNG NÀY ĐỂ KẾT NỐI ĐƯỢC VỚI AIVEN MYSQL
            PDO::MYSQL_ATTR_SSL_CA => true,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]);

        return self::$pdo;
    }

    public static function ping(): bool
    {
        return (int)self::connection()->query('SELECT 1')->fetchColumn() === 1;
    }
}
