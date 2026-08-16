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
        $pass = Env::get('DB_PASS') ?? Env::get('DB_PASSWORD', '');

        if (!$host || !$name || !$user) {
            throw new RuntimeException("Missing DB config: Host={$host}, Name={$name}, User={$user}");
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Đọc chứng chỉ SSL từ đường dẫn Secret File của Render
        $caPath = '/etc/secrets/aiven-ca.pem';
        if (file_exists($caPath)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        } else {
            $options[PDO::MYSQL_ATTR_SSL_CA] = true;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        self::$pdo = new PDO($dsn, $user, (string)$pass, $options);

        return self::$pdo;
    }

    public static function ping(): bool
    {
        return (int)self::connection()->query('SELECT 1')->fetchColumn() === 1;
    }
}
