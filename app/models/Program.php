<?php

declare(strict_types=1);

namespace App\Models;

final class Program
{
    public static function active(): array
    {
        return db()->query('SELECT * FROM programs WHERE is_active = 1 ORDER BY code')->fetchAll();
    }

    public static function directorAdded(): array
    {
        return db()->query('SELECT * FROM programs WHERE is_active = 1 AND director_added = 1 ORDER BY code')->fetchAll();
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM programs ORDER BY code')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM programs WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findDirectorAdded(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM programs WHERE id = :id AND is_active = 1 AND director_added = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $code, string $name, bool $directorAdded = false): int
    {
        $code = strtoupper(trim($code));
        $name = trim($name);
        if ($code === '' || $name === '' || strlen($code) > 16 || strlen($name) > 128 || !preg_match('/^[A-Z0-9-]{2,16}$/', $code)) {
            throw new \InvalidArgumentException('Enter a department code of 2 to 16 letters or numbers, and a department name.');
        }
        $existing = self::findByCodeOrName($code);
        if ($existing) {
            return (int) $existing['id'];
        }
        $stmt = db()->prepare('INSERT INTO programs (code, name, is_active, director_added) VALUES (:code, :name, 1, :dir)');
        $stmt->execute(['code' => $code, 'name' => $name, 'dir' => $directorAdded ? 1 : 0]);
        return (int) db()->lastInsertId();
    }

    public static function findByCodeOrName(string $value): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM programs WHERE is_active = 1 AND (code = :code OR name = :name) LIMIT 1'
        );
        $stmt->execute(['code' => $value, 'name' => $value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
