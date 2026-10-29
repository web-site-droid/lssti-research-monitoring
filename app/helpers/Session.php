<?php

declare(strict_types=1);

namespace App\Helpers;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (defined('VERCEL_DEPLOY') && VERCEL_DEPLOY);

        if (defined('VERCEL_DEPLOY') && VERCEL_DEPLOY) {
            session_set_save_handler(new DbSession(), true);
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        self::enforceTimeout();
    }

    public static function enforceTimeout(): void
    {
        $timeoutMinutes = (int) (Settings::get('session_timeout_minutes') ?? SESSION_TIMEOUT_MINUTES);
        $now = time();
        if (isset($_SESSION['_last_activity'])) {
            if ($now - (int) $_SESSION['_last_activity'] > $timeoutMinutes * 60) {
                $_SESSION = [];
                session_regenerate_id(true);
            }
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
