<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Pipeline (low → high):
 * student → research_adviser → dept_research_coordinator → dean
 * → school_research_coordinator → school_administrator
 */
final class Role
{
    public const STUDENT = 'student';
    public const RESEARCH_ADVISER = 'research_adviser';
    public const DEPT_RESEARCH_COORDINATOR = 'dept_research_coordinator';
    public const DEAN = 'dean';
    public const SCHOOL_RESEARCH_COORDINATOR = 'school_research_coordinator';
    public const LIBRARIAN = 'librarian';
    public const SCHOOL_ADMINISTRATOR = 'school_administrator';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::STUDENT,
            self::RESEARCH_ADVISER,
            self::DEPT_RESEARCH_COORDINATOR,
            self::DEAN,
            self::SCHOOL_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
            self::SCHOOL_ADMINISTRATOR,
        ];
    }

    /** @return list<string> */
    public static function peopleManagers(): array
    {
        return [
            self::DEAN,
            self::SCHOOL_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
            self::SCHOOL_ADMINISTRATOR,
        ];
    }

    /** Roles that belong to a department (program_id required). */
    /** @return list<string> */
    public static function departmentBoundRoles(): array
    {
        return [
            self::STUDENT,
            self::RESEARCH_ADVISER,
            self::DEPT_RESEARCH_COORDINATOR,
            self::DEAN,
        ];
    }

    /** Accounts a dean may create in their own department. */
    /** @return list<string> */
    public static function deanCreatableRoles(): array
    {
        return [
            self::STUDENT,
            self::RESEARCH_ADVISER,
            self::DEPT_RESEARCH_COORDINATOR,
        ];
    }

    /** Accounts the librarian may create. */
    /** @return list<string> */
    public static function schoolAdminCreatableRoles(): array
    {
        return [
            self::DEAN,
            self::SCHOOL_RESEARCH_COORDINATOR,
        ];
    }

    /** Librarian and school research coordinator may add or rename departments. */
    public static function canManageDepartments(string $slug, string $name = ''): bool
    {
        $role = self::canonical($slug, $name);
        return in_array($role, [
            self::SCHOOL_ADMINISTRATOR,
            self::SCHOOL_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
        ], true);
    }

    public static function canSeeArticleReaders(?array $user, array $study): bool
    {
        if (!$user) {
            return false;
        }
        $role = self::canonical((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
        if (in_array($role, [self::SCHOOL_ADMINISTRATOR, self::SCHOOL_RESEARCH_COORDINATOR, self::LIBRARIAN], true)) {
            return true;
        }
        return in_array($role, [self::DEAN, self::DEPT_RESEARCH_COORDINATOR], true)
            && self::sameDepartment($user, $study);
    }

    /** Department gate after adviser (status: pending). */
    /** @return list<string> */
    public static function deptReviewers(): array
    {
        return [
            self::DEPT_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
        ];
    }

    /** @return list<string> */
    public static function deanReviewers(): array
    {
        return [
            self::DEAN,
            self::LIBRARIAN,
        ];
    }

    /** @return list<string> */
    public static function advisers(): array
    {
        return [
            self::RESEARCH_ADVISER,
            self::LIBRARIAN,
        ];
    }

    /** School research coordinator (ethics / plagiarism). */
    /** @return list<string> */
    public static function academicApprovers(): array
    {
        return [
            self::SCHOOL_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
        ];
    }

    /** Legacy library routes. Librarian is no longer a review gate. */
    /** @return list<string> */
    public static function librarians(): array
    {
        return [
            self::LIBRARIAN,
        ];
    }

    /** Final publish by librarian. */
    /** @return list<string> */
    public static function finalApprovers(): array
    {
        return [
            self::LIBRARIAN,
            self::SCHOOL_ADMINISTRATOR,
        ];
    }

    /** @return list<string> */
    public static function allManuscriptViewers(): array
    {
        return [
            self::SCHOOL_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
            self::SCHOOL_ADMINISTRATOR,
        ];
    }

    /** @return list<string> */
    public static function reporters(): array
    {
        return [
            self::DEPT_RESEARCH_COORDINATOR,
            self::DEAN,
            self::SCHOOL_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
        ];
    }

    public static function isAdmin(string $slug): bool
    {
        $role = self::canonical($slug);
        return in_array($role, [self::LIBRARIAN, self::SCHOOL_ADMINISTRATOR], true);
    }

    /** Map legacy Hostinger/XAMPP slugs or display names onto the current role keys. */
    public static function canonical(string $slug, string $name = ''): string
    {
        $blob = strtolower(trim($slug . ' ' . $name));
        $key = strtolower(trim(str_replace([' ', '-'], '_', $slug)));
        if (
            str_contains($blob, 'research director')
            || $key === 'research_director'
            || $key === 'director'
            || $key === 'researchdirector'
            || $key === 'school_admin'
            || $key === 'administrator'
            || $key === 'school_administrator'
        ) {
            return self::SCHOOL_ADMINISTRATOR;
        }
        return match ($key) {
            'adviser', 'advisor', 'teacher' => self::RESEARCH_ADVISER,
            'dept_coordinator', 'department_research_coordinator', 'drc' => self::DEPT_RESEARCH_COORDINATOR,
            'src', 'school_coordinator' => self::SCHOOL_RESEARCH_COORDINATOR,
            'library', 'librarian' => self::LIBRARIAN,
            'school_administrator' => self::SCHOOL_ADMINISTRATOR,
            default => $key !== '' ? $key : $slug,
        };
    }

    public static function label(string $slug, string $name = ''): string
    {
        if (in_array(strtolower(trim($name)), ['school administrator', 'school admin'], true) && trim($slug) === '') {
            return 'Librarian';
        }
        return match (self::canonical($slug, $name)) {
            self::STUDENT => 'Student',
            self::RESEARCH_ADVISER => 'Research Adviser',
            self::DEPT_RESEARCH_COORDINATOR => 'Dept Research Coordinator',
            self::DEAN => 'Dean',
            self::SCHOOL_RESEARCH_COORDINATOR => 'School Research Coordinator',
            self::LIBRARIAN => 'Librarian',
            self::SCHOOL_ADMINISTRATOR => 'Librarian',
            default => ($name !== '' ? $name : $slug),
        };
    }

    /**
     * Repair old role slugs/names and demo ID nos. after an upload.
     * Safe to run on every request; it only writes when something is outdated.
     */
    public static function repairStoredRoles(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $pdo = db();
            $pdo->exec(
                "UPDATE roles SET name = 'Librarian'
                 WHERE LOWER(name) IN ('research director', 'director')
                    OR LOWER(REPLACE(name, ' ', '_')) IN ('research_director')"
            );
            $renames = [
                'research_director' => [self::SCHOOL_ADMINISTRATOR, 'Librarian'],
                'director' => [self::SCHOOL_ADMINISTRATOR, 'Librarian'],
                'adviser' => [self::RESEARCH_ADVISER, 'Research Adviser'],
                'advisor' => [self::RESEARCH_ADVISER, 'Research Adviser'],
            ];
            foreach ($renames as $from => [$to, $name]) {
                $old = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
                $old->execute(['slug' => $from]);
                $oldId = $old->fetchColumn();
                if (!$oldId) {
                    continue;
                }
                $new = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
                $new->execute(['slug' => $to]);
                $newId = $new->fetchColumn();
                if ($newId && (int) $newId !== (int) $oldId) {
                    $pdo->prepare('UPDATE users SET role_id = :n WHERE role_id = :o')->execute([
                        'n' => (int) $newId,
                        'o' => (int) $oldId,
                    ]);
                } else {
                    $pdo->prepare('UPDATE roles SET slug = :slug, name = :name WHERE id = :id')->execute([
                        'slug' => $to,
                        'name' => $name,
                        'id' => (int) $oldId,
                    ]);
                }
            }
            $labels = [
                self::STUDENT => 'Student',
                self::RESEARCH_ADVISER => 'Research Adviser',
                self::DEPT_RESEARCH_COORDINATOR => 'Dept Research Coordinator',
                self::DEAN => 'Dean',
                self::SCHOOL_RESEARCH_COORDINATOR => 'School Research Coordinator',
                self::LIBRARIAN => 'Librarian',
                self::SCHOOL_ADMINISTRATOR => 'Librarian',
            ];
            $upd = $pdo->prepare('UPDATE roles SET name = :name WHERE slug = :slug AND name <> :name2');
            foreach ($labels as $slug => $name) {
                $upd->execute(['name' => $name, 'slug' => $slug, 'name2' => $name]);
            }
            $accounts = [
                'STU-0001' => self::STUDENT,
                'ADV-0001' => self::RESEARCH_ADVISER,
                'LIB-0001' => self::DEPT_RESEARCH_COORDINATOR,
                'DEAN-0001' => self::DEAN,
                'SRC-0001' => self::SCHOOL_RESEARCH_COORDINATOR,
                'RD-0001' => self::SCHOOL_ADMINISTRATOR,
            ];
            $assign = $pdo->prepare(
                'UPDATE users u INNER JOIN roles r ON r.slug = :slug SET u.role_id = r.id WHERE u.school_id = :sid'
            );
            foreach ($accounts as $schoolId => $slug) {
                $assign->execute(['slug' => $slug, 'sid' => $schoolId]);
            }
            $libRole = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
            $libRole->execute(['slug' => self::LIBRARIAN]);
            $libRoleId = $libRole->fetchColumn();
            $srcRole = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
            $srcRole->execute(['slug' => self::SCHOOL_RESEARCH_COORDINATOR]);
            $srcRoleId = $srcRole->fetchColumn();
            if ($libRoleId && $srcRoleId && (int) $libRoleId !== (int) $srcRoleId) {
                $pdo->prepare('UPDATE users SET role_id = :n WHERE role_id = :o')->execute([
                    'n' => (int) $srcRoleId,
                    'o' => (int) $libRoleId,
                ]);
            }
            $pdo->exec("UPDATE studies SET status = 'admin_review', updated_at = NOW() WHERE status = 'library_review'");
            $status = $pdo->query("SHOW COLUMNS FROM studies LIKE 'status'")->fetch();
            $type = (string) ($status['Type'] ?? '');
            if ($type !== '' && !str_contains($type, 'dean_review')) {
                $pdo->exec(
                    "ALTER TABLE studies MODIFY status ENUM(
                        'draft','submitted','adviser_reviewed','pending','dean_review','ethics_review','under_review',
                        'library_review','admin_review','revision_requested','resubmitted','approved','archived'
                    ) NOT NULL DEFAULT 'draft'"
                );
            }
        } catch (\Throwable $e) {
            // Hostinger should still load even if this repair cannot run.
        }
    }

    public static function canViewAllManuscripts(string $slug, string $name = ''): bool
    {
        $role = self::canonical($slug, $name);
        return in_array($role, [
            self::SCHOOL_RESEARCH_COORDINATOR,
            self::LIBRARIAN,
            self::SCHOOL_ADMINISTRATOR,
        ], true);
    }

    public static function sameDepartment(?array $user, array $study): bool
    {
        if (!$user) {
            return false;
        }

        $userProgram = (int) ($user['program_id'] ?? 0);
        $studyProgram = (int) ($study['program_id'] ?? 0);
        if ($userProgram <= 0 || $studyProgram <= 0) {
            return false;
        }

        return $userProgram === $studyProgram;
    }
}
