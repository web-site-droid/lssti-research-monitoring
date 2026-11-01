<?php

declare(strict_types=1);

namespace App\Models;

final class LoginLog
{
    public static function record(?int $userId, ?string $schoolIdAttempt, string $event): void
    {
        $stmt = db()->prepare(
            'INSERT INTO login_logs (user_id, school_id_attempt, event, ip_address, user_agent, created_at)
             VALUES (:uid, :sid, :ev, :ip, :ua, NOW())'
        );
        $stmt->execute([
            'uid' => $userId,
            'sid' => $schoolIdAttempt,
            'ev' => $event,
            'ip' => client_ip(),
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
        ]);
    }

    public static function recent(int $limit = 200): array
    {
        $stmt = db()->prepare(
            'SELECT l.*, u.school_id, u.first_name, u.last_name
             FROM login_logs l
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
