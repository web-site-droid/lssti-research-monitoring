<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;

final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function get(string $key): ?string
    {
        self::load();
        return self::$cache[$key] ?? null;
    }

    public static function getInt(string $key, int $default): int
    {
        $v = self::get($key);
        return $v !== null ? (int) $v : $default;
    }

    public static function getFloat(string $key, float $default): float
    {
        $v = self::get($key);
        return $v !== null ? (float) $v : $default;
    }

    public static function set(string $key, string $value, ?int $userId = null): void
    {
        $stmt = db()->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at, updated_by)
             VALUES (:k, :v, NOW(), :uid)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW(), updated_by = VALUES(updated_by)'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'uid' => $userId]);
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable) {
            // DB may not exist during first install
        }
    }
}
