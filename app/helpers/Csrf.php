<?php

declare(strict_types=1);

namespace App\Helpers;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_KEY])) {
            $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validate(?string $token): bool
    {
        $sessionToken = $_SESSION[CSRF_TOKEN_KEY] ?? '';
        return is_string($token) && $token !== '' && hash_equals($sessionToken, $token);
    }

    public static function requireValid(): void
    {
        $token = $_POST['_csrf'] ?? null;
        if (!self::validate(is_string($token) ? $token : null)) {
            http_response_code(403);
            echo 'Invalid security token. Go back and try again.';
            exit;
        }
    }
}
