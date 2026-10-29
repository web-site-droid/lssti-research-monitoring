<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\User;

final class Auth
{
    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        return User::findById((int) $id);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_slug'] = Role::canonical((string) ($user['role_slug'] ?? ''));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login');
        }
    }

    /** @param string|list<string> $roles */
    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();
        $roles = is_array($roles) ? $roles : [$roles];
        $current = Role::canonical((string) ($_SESSION['role_slug'] ?? ''));
        if (!in_array($current, $roles, true)) {
            http_response_code(403);
            view('errors/forbidden', ['message' => 'You do not have permission to access this page.']);
            exit;
        }
    }

    public static function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $current = Role::canonical((string) ($_SESSION['role_slug'] ?? ''));
        return in_array($current, $roles, true);
    }
}
