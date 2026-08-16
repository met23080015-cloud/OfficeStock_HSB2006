<?php
declare(strict_types=1);

final class Audit
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        try {
            $stmt = Database::connection()->prepare(
                "INSERT INTO audit_logs
                 (user_id,action,entity_type,entity_id,details_json,ip_address)
                 VALUES(?,?,?,?,?,?)"
            );
            $stmt->execute([
                Auth::user()['id'] ?? null,
                strtoupper($action),
                $entityType,
                $entityId,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45) ?: null,
            ]);
        } catch (Throwable) {
            // Audit failure must never break a business transaction.
        }
    }
}
