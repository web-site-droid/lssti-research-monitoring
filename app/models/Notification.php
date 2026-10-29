<?php

declare(strict_types=1);

namespace App\Models;

final class Notification
{
    public static function create(int $userId, string $type, string $title, ?string $body, ?string $linkRoute): void
    {
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, type, title, body, link_route, is_read, created_at)
             VALUES (:uid, :type, :title, :body, :link, 0, NOW())'
        );
        $stmt->execute([
            'uid' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $linkRoute,
        ]);
    }

    public static function forUser(int $userId, int $limit = 50): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $id, int $userId): void
    {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
    }
}
