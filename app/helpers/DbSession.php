<?php

declare(strict_types=1);

namespace App\Helpers;

final class DbSession implements \SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = db()->prepare(
            'SELECT data FROM php_sessions WHERE id = :id AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetchColumn();
        return is_string($data) ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $minutes = defined('SESSION_TIMEOUT_MINUTES') ? (int) SESSION_TIMEOUT_MINUTES : 30;
        $expires = date('Y-m-d H:i:s', time() + max(5, $minutes) * 60);
        $stmt = db()->prepare(
            'REPLACE INTO php_sessions (id, data, expires_at) VALUES (:id, :data, :expires)'
        );
        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'expires' => $expires,
        ]);
    }

    public function destroy(string $id): bool
    {
        $stmt = db()->prepare('DELETE FROM php_sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        return (int) db()->exec('DELETE FROM php_sessions WHERE expires_at < NOW()');
    }
}
