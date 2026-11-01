<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

final class RateLimit
{
    public static function isLoginLocked(string $ip): bool
    {
        return false;
    }
}
