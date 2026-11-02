
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

        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                "Cannot start session: output already began in {$file} on line {$line}."
            );
        }

        $https = (
            !empty($_SERVER['HTTPS'])
            && strtolower((string) $_SERVER['HTTPS']) !== 'off'
        )
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

        if (!session_start()) {
            throw new \RuntimeException('Unable to start the session.');
        }

        self::enforceTimeout();
    }

    public static function enforceTimeout(): void
    {
        $timeoutMinutes = defined('SESSION_TIMEOUT_MINUTES')
            ? (int) SESSION_TIMEOUT_MINUTES
            : 30;

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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
