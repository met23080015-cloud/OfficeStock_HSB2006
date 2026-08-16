<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static ?string $tokenHash = null;

    public static function attempt(string $email, string $password): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            "SELECT u.id,u.full_name,u.email,u.status,u.department_id,u.password_hash,
                    r.code AS role_code,r.name AS role_name,d.name AS department_name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             LEFT JOIN departments d ON d.id=u.department_id
             WHERE LOWER(u.email)=?
             LIMIT 1"
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'ACTIVE' || !password_verify($password, $user['password_hash'])) {
            usleep(150000);
            throw new RuntimeException('Invalid email/password or the account is locked.');
        }

        unset($user['password_hash']);
        self::purgeExpired();

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $hours = max(1, Env::int('SESSION_TTL_HOURS', 8));

        $insert = $pdo->prepare(
            "INSERT INTO auth_sessions(user_id,token_hash,expires_at,last_used_at,user_agent_hash,ip_address)
             VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? HOUR),UTC_TIMESTAMP(),?,?)"
        );
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $insert->execute([
            (int)$user['id'],
            $tokenHash,
            $hours,
            $ua !== '' ? hash('sha256', $ua) : null,
            substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45) ?: null,
        ]);

        self::$user = $user;
        self::$tokenHash = $tokenHash;

        Audit::log('LOGIN', 'user', (int)$user['id']);
        return ['token' => $rawToken, 'user' => $user, 'expires_in_seconds' => $hours * 3600];
    }

    public static function user(): ?array
    {
        if (self::$user !== null) return self::$user;

        $token = Request::bearerToken();
        if (!$token || strlen($token) < 32) return null;

        $tokenHash = hash('sha256', $token);
        $stmt = Database::connection()->prepare(
            "SELECT u.id,u.full_name,u.email,u.status,u.department_id,
                    r.code AS role_code,r.name AS role_name,d.name AS department_name,
                    s.token_hash,s.expires_at
             FROM auth_sessions s
             JOIN users u ON u.id=s.user_id
             JOIN roles r ON r.id=u.role_id
             LEFT JOIN departments d ON d.id=u.department_id
             WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP() AND u.status='ACTIVE'
             LIMIT 1"
        );
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();
        if (!$user) return null;

        unset($user['token_hash'], $user['expires_at']);
        self::$user = $user;
        self::$tokenHash = $tokenHash;

        Database::connection()->prepare(
            "UPDATE auth_sessions SET last_used_at=UTC_TIMESTAMP() WHERE token_hash=?"
        )->execute([$tokenHash]);

        return self::$user;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) Response::error('Authentication required.', 401, 'UNAUTHENTICATED');
        return $user;
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::requireUser();
        $allowed = array_map('strtoupper', $roles);
        if (!in_array(strtoupper((string)$user['role_code']), $allowed, true)) {
            Response::error('You do not have permission for this action.', 403, 'FORBIDDEN');
        }
        return $user;
    }

    public static function role(): ?string
    {
        return self::user()['role_code'] ?? null;
    }

    public static function logout(): void
    {
        $user = self::user();
        if (self::$tokenHash) {
            Database::connection()->prepare("DELETE FROM auth_sessions WHERE token_hash=?")
                ->execute([self::$tokenHash]);
        }
        if ($user) Audit::log('LOGOUT', 'user', (int)$user['id']);
        self::$user = null;
        self::$tokenHash = null;
    }

    private static function purgeExpired(): void
    {
        try {
            Database::connection()->exec("DELETE FROM auth_sessions WHERE expires_at<=UTC_TIMESTAMP()");
        } catch (Throwable) {
        }
    }
}
