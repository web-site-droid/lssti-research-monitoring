<?php
declare(strict_types=1);
namespace App\Models;

final class LibrarianAccounts
{
    public static function all(): array
    {
        return array_values(array_filter(User::all(), static fn (array $user): bool =>
            ($user['role_slug'] ?? '') === 'school_administrator'
        ));
    }

    public static function adminExists(): bool
    {
        return (bool) db()->query("SELECT 1 FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='account_admin' LIMIT 1")->fetchColumn();
    }

    public static function createAdmin(array $data, int $actor): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Serialize first-admin setup using the existing final-publisher role row.
            $pdo->query("SELECT id FROM roles WHERE slug='school_administrator' FOR UPDATE")->fetchAll();
            if (self::adminExists()) {
                throw new \RuntimeException('An Admin account already exists. Setup is closed.');
            }
            $role = User::findRoleBySlug('account_admin');
            if (!$role) {
                $pdo->exec("INSERT INTO roles (slug, name) VALUES ('account_admin', 'Admin')");
                $role = User::findRoleBySlug('account_admin');
            }
            $data['role_id'] = (int) $role['id'];
            $data['program_id'] = null;
            $id = User::create($data, $actor);
            $pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function activity(?int $userId = null): array
    {
        $sql = "SELECT a.id, a.user_id, a.action, a.created_at, u.school_id, u.first_name, u.last_name
                FROM audit_logs a JOIN users u ON u.id=a.user_id JOIN roles r ON r.id=u.role_id
                WHERE r.slug='school_administrator'";
        $params = [];
        if ($userId !== null) { $sql .= ' AND u.id=:uid'; $params['uid']=$userId; }
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT 200';
        $stmt = db()->prepare($sql); $stmt->execute($params);
        $activity = $stmt->fetchAll();
        $sql = "SELECT l.id, l.user_id, l.event AS action, l.created_at, u.school_id, u.first_name, u.last_name
                FROM login_logs l JOIN users u ON u.id=l.user_id JOIN roles r ON r.id=u.role_id
                WHERE r.slug='school_administrator'";
        if ($userId !== null) $sql .= ' AND u.id=:uid';
        $sql .= ' ORDER BY l.created_at DESC, l.id DESC LIMIT 200';
        $stmt = db()->prepare($sql); $stmt->execute($params);
        $activity = array_merge($activity, $stmt->fetchAll());
        usort($activity, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return array_slice($activity, 0, 200);
    }
}
