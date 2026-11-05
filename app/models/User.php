<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Role;
use PDO;

final class User
{
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(self::selectSql() . ' WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? self::withRoleLabel($row) : null;
    }

    public static function findBySchoolId(string $schoolId): ?array
    {
        $sid = strtoupper(trim($schoolId));
        $stmt = db()->prepare(self::selectSql() . ' WHERE UPPER(TRIM(u.school_id)) = :sid LIMIT 1');
        $stmt->execute(['sid' => $sid]);
        $row = $stmt->fetch();
        return $row ? self::withRoleLabel($row) : null;
    }

    public static function studentsInProgram(int $programId): array
    {
        $stmt = db()->prepare(
            self::selectSql() . ' WHERE r.slug = :slug AND u.program_id = :pid ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['slug' => 'student', 'pid' => $programId]);
        return self::withRoleLabels($stmt->fetchAll());
    }

    /** @param list<string>|null $roleSlugs */
    public static function inProgram(int $programId, ?array $roleSlugs = null): array
    {
        $roleSlugs = $roleSlugs ?: ['student', 'research_adviser'];
        $placeholders = [];
        $params = ['pid' => $programId];
        foreach (array_values($roleSlugs) as $i => $slug) {
            $key = 'r' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $slug;
        }
        $stmt = db()->prepare(
            self::selectSql() . ' WHERE r.slug IN (' . implode(',', $placeholders) . ') AND u.program_id = :pid ORDER BY r.slug, u.last_name, u.first_name'
        );
        $stmt->execute($params);
        return self::withRoleLabels($stmt->fetchAll());
    }

    public static function all(?string $roleSlug = null): array
    {
        if ($roleSlug) {
            $stmt = db()->prepare(
                self::selectSql() . ' WHERE r.slug = :slug ORDER BY u.last_name, u.first_name'
            );
            $stmt->execute(['slug' => $roleSlug]);
            return self::withRoleLabels($stmt->fetchAll());
        }
        $sql = self::selectSql() . ' ORDER BY u.last_name, u.first_name';
        return self::withRoleLabels(db()->query($sql)->fetchAll());
    }

    public static function create(array $data, int $createdBy): int
    {
        $stmt = db()->prepare(
            'INSERT INTO users (school_id, password_hash, first_name, middle_name, last_name, role_id, program_id, is_active, must_change_password, created_by)
             VALUES (:sid, :ph, :fn, :mn, :ln, :rid, :pid, :active, 1, :cb)'
        );
        $stmt->execute([
            'sid' => $data['school_id'],
            'ph' => self::hashPassword((string) $data['password']),
            'fn' => $data['first_name'],
            'mn' => $data['middle_name'] ?: null,
            'ln' => $data['last_name'],
            'rid' => $data['role_id'],
            'pid' => $data['program_id'] ?? null,
            'active' => $data['is_active'] ?? 1,
            'cb' => $createdBy,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $identitySql = array_key_exists('school_id', $data) ? ', school_id = :sid' : '';
        $stmt = db()->prepare(
            'UPDATE users SET first_name = :fn, middle_name = :mn, last_name = :ln, role_id = :rid, program_id = :pid, is_active = :active, updated_at = NOW()
             ' . $identitySql . ' WHERE id = :id'
        );
        $params = [
            'fn' => $data['first_name'],
            'mn' => $data['middle_name'] ?: null,
            'ln' => $data['last_name'],
            'rid' => $data['role_id'],
            'pid' => $data['program_id'] ?? null,
            'active' => $data['is_active'],
            'id' => $id,
        ];
        if ($identitySql !== '') $params['sid'] = $data['school_id'];
        $stmt->execute($params);
    }

    public static function deleteWithoutAcademicRecords(int $id, int $actorId, ?string $requiredRole = null): array
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->query("SELECT id FROM roles WHERE slug='school_administrator' FOR UPDATE")->fetchAll();
            $stmt = $pdo->prepare('SELECT u.id, u.school_id, u.is_active, r.slug AS role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=:id FOR UPDATE');
            $stmt->execute(['id'=>$id]);
            $account = $stmt->fetch();
            if (!$account) throw new \RuntimeException('Account not found.');
            if ($requiredRole !== null && $account['role_slug'] !== $requiredRole) throw new \RuntimeException('Only Librarian accounts can be deleted here.');
            if ($id === $actorId) throw new \RuntimeException('You cannot delete your signed-in account.');
            if ($account['role_slug'] === Role::ACCOUNT_ADMIN) throw new \RuntimeException('Admin accounts cannot be deleted through People.');
            if ($account['role_slug'] === Role::SCHOOL_ADMINISTRATOR && (int) $account['is_active'] === 1) {
                LibrarianAccounts::requireAnotherActive($id);
            }
            $references = [
                'studies' => ['submitter_user_id', 'adviser_user_id'],
                'study_versions' => ['uploaded_by'],
                'review_assignments' => ['reviewer_user_id', 'assigned_by'],
                'review_comments' => ['reviewer_user_id'],
                'review_scores' => ['reviewer_user_id'],
                'plagiarism_results' => ['recorded_by'],
                'read_logs' => ['user_id'],
            ];
            foreach ($references as $table => $columns) {
                foreach ($columns as $column) {
                    $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE $column=:id LIMIT 1");
                    $stmt->execute(['id'=>$id]);
                    if ($stmt->fetchColumn()) throw new \RuntimeException('This account has study, review, or reading records. Deactivate it using Edit instead of deleting it.');
                }
            }
            $stmt = $pdo->prepare('DELETE FROM users WHERE id=:id');
            $stmt->execute(['id'=>$id]);
            $pdo->commit();
            return $account;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function resetPassword(int $id, string $plain): void
    {
        $stmt = db()->prepare(
            'UPDATE users SET password_hash = :ph, must_change_password = 1, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['ph' => self::hashPassword($plain), 'id' => $id]);
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function passwordMatches(string $stored, string $plain): bool
    {
        if ($stored === '' || $plain === '') {
            return false;
        }
        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
            return password_verify($plain, $stored);
        }
        return hash_equals($stored, $plain);
    }

    public static function updateProfile(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE users SET first_name = :fn, middle_name = :mn, last_name = :ln,
             contact_number = :contact, email = :email, address = :address,
             city = :city, state = :state, program_id = :pid, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'fn' => $data['first_name'],
            'mn' => $data['middle_name'] ?: null,
            'ln' => $data['last_name'],
            'contact' => $data['contact_number'] ?: null,
            'email' => $data['email'] ?: null,
            'address' => $data['address'] ?: null,
            'city' => $data['city'] ?: null,
            'state' => $data['state'] ?: null,
            'pid' => isset($data['program_id']) && (int) $data['program_id'] > 0 ? (int) $data['program_id'] : null,
            'id' => $id,
        ]);
    }

    public static function updatePhoto(int $id, string $relativePath): void
    {
        $stmt = db()->prepare('UPDATE users SET photo_path = :path, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['path' => $relativePath, 'id' => $id]);
    }

    public static function updatePassword(int $id, string $plain, bool $mustChange = false): void
    {
        $stmt = db()->prepare(
            'UPDATE users SET password_hash = :ph, must_change_password = :mc, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'ph' => self::hashPassword($plain),
            'mc' => $mustChange ? 1 : 0,
            'id' => $id,
        ]);
    }

    public static function roles(): array
    {
        $rows = db()->query('SELECT * FROM roles ORDER BY id')->fetchAll();
        foreach ($rows as &$row) {
            $row['name'] = Role::label((string) $row['slug']);
        }
        unset($row);
        return $rows;
    }

    public static function roleExists(int $roleId): bool
    {
        return self::findRole($roleId) !== null;
    }

    public static function findRoleBySlug(string $slug): ?array
    {
        $stmt = db()->prepare('SELECT * FROM roles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRole(int $roleId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $roleId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function selectSql(): string
    {
        return 'SELECT u.*, r.slug AS role_slug, r.name AS role_name, p.code AS program_code, p.name AS program_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN programs p ON p.id = u.program_id';
    }

    /** @param array<string, mixed> $row */
    private static function withRoleLabel(array $row): array
    {
        $row['role_slug'] = Role::canonical((string) ($row['role_slug'] ?? ''), (string) ($row['role_name'] ?? ''));
        $row['role_name'] = Role::label($row['role_slug'], (string) ($row['role_name'] ?? ''));
        return $row;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function withRoleLabels(array $rows): array
    {
        return array_map([self::class, 'withRoleLabel'], $rows);
    }
}
