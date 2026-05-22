<?php
declare(strict_types=1);

namespace Mori;

final class AuditLog
{
    public static function log(?int $userId, string $action, ?string $entity = null,
                               ?int $entityId = null, ?string $details = null): void
    {
        try {
            Database::instance()->insert('audit_log', [
                'user_id'    => $userId,
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'details'    => $details,
                'ip_address' => Auth::clientIp(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Audit log must never break the app
            error_log('AuditLog failed: ' . $e->getMessage());
        }
    }

    public static function recent(int $limit = 50): array
    {
        // LIMIT with named placeholder fails when PDO emulation is off,
        // so cast and interpolate the limit safely.
        $limit = max(1, min($limit, 1000));
        return Database::instance()->fetchAll(
            'SELECT a.*, u.name AS user_name, u.email AS user_email
               FROM audit_log a
               LEFT JOIN users u ON u.id = a.user_id
              ORDER BY a.created_at DESC
              LIMIT ' . $limit
        );
    }
}
