<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

final class RateLimit
{
    public static function isLoginLocked(string $ip): bool
    {
        $max = max(1, Settings::getInt('login_max_attempts', DEFAULT_LOGIN_MAX_ATTEMPTS));
        $minutes = max(1, Settings::getInt('login_lockout_minutes', DEFAULT_LOGIN_LOCKOUT_MINUTES));
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM login_logs
             WHERE ip_address = :ip AND event = :event
               AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':event', 'login_failed');
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn() >= $max;
    }
}
