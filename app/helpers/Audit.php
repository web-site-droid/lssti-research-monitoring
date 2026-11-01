<?php

declare(strict_types=1);

namespace App\Helpers;

final class Audit
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $details = null): void
    {
        $user = Auth::user();
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
             VALUES (:uid, :action, :etype, :eid, :details, :ip, NOW())'
        );
        $stmt->execute([
            'uid' => $user ? (int) $user['id'] : null,
            'action' => $action,
            'etype' => $entityType,
            'eid' => $entityId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'ip' => client_ip(),
        ]);
        $localId = (int) db()->lastInsertId();
        SupabaseSync::pushAudit(
            $localId,
            $user ? ($user['school_id'] ?? null) : null,
            $action,
            $entityType,
            $entityId,
            $details,
            client_ip()
        );
    }
}
