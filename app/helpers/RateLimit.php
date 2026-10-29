<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

final class RateLimit
{
    public static function isLoginLocked(string $ip): bool
    {
        $max = Settings::getInt('login_max_attempts', DEFAULT_LOGIN_MAX_ATTEMPTS);
        $minutes = Settings::getInt('login_lockout_minutes', DEFAULT_LOGIN_LOCKOUT_MINUTES);
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS c FROM login_logs
             WHERE ip_address = :ip AND event = :ev
             AND created_at > DATE_SUB(NOW(), INTERVAL :mins MINUTE)'
        );
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':ev', 'login_failed');
        $stmt->bindValue(':mins', $minutes, PDO::PARAM_INT);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
        return $count >= $max;
    }
}
