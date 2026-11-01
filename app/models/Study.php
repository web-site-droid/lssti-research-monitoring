<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Auth;
use PDO;

final class Study
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT s.*, p.code AS program_code, p.name AS program_name,
                    u.first_name AS submitter_first, u.last_name AS submitter_last
             FROM studies s
             INNER JOIN programs p ON p.id = s.program_id
             INNER JOIN users u ON u.id = s.submitter_user_id
             WHERE s.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function authors(int $studyId): array
    {
        $stmt = db()->prepare('SELECT * FROM study_authors WHERE study_id = :id ORDER BY sort_order');
        $stmt->execute(['id' => $studyId]);
        return $stmt->fetchAll();
    }

    public static function keywords(int $studyId): array
    {
        $stmt = db()->prepare('SELECT keyword FROM study_keywords WHERE study_id = :id ORDER BY keyword');
        $stmt->execute(['id' => $studyId]);
        return array_column($stmt->fetchAll(), 'keyword');
    }

    public static function versions(int $studyId): array
    {
        $stmt = db()->prepare(
            'SELECT v.*, u.first_name, u.last_name FROM study_versions v
             INNER JOIN users u ON u.id = v.uploaded_by
             WHERE v.study_id = :id ORDER BY v.version_number DESC'
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->fetchAll();
    }

    public static function comments(int $studyId, bool $publicOnly = false): array
    {
        $sql = 'SELECT c.*, u.first_name, u.last_name FROM review_comments c
             INNER JOIN users u ON u.id = c.reviewer_user_id
             WHERE c.study_id = :id';
        if ($publicOnly) {
            $sql .= " AND c.visibility = 'public'";
        }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $studyId]);
        return $stmt->fetchAll();
    }

    public static function scores(int $studyId): array
    {
        $stmt = db()->prepare(
            'SELECT sc.*, u.first_name, u.last_name FROM review_scores sc
             INNER JOIN users u ON u.id = sc.reviewer_user_id
             WHERE sc.study_id = :id ORDER BY sc.created_at DESC'
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->fetchAll();
    }

    public static function ethics(int $studyId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM ethics_checklists WHERE study_id = :id');
        $stmt->execute(['id' => $studyId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function latestPlagiarism(int $studyId, ?int $versionId = null): ?array
    {
        if ($versionId) {
            $stmt = db()->prepare(
                'SELECT * FROM plagiarism_results WHERE study_id = :sid AND study_version_id = :vid ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['sid' => $studyId, 'vid' => $versionId]);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM plagiarism_results WHERE study_id = :sid ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute(['sid' => $studyId]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $filters */
    public static function listForUser(array $user, array $filters = []): array
    {
        $role = $user['role_slug'];
        $sql = "SELECT s.*, p.code AS program_code,
                u.first_name AS submitter_first, u.last_name AS submitter_last,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(a.last_name, ', ', a.first_name,
                            IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                        ORDER BY a.sort_order SEPARATOR '; '
                    )
                    FROM study_authors a
                    WHERE a.study_id = s.id
                ) AS authors
                FROM studies s
                INNER JOIN programs p ON p.id = s.program_id
                INNER JOIN users u ON u.id = s.submitter_user_id
                WHERE 1=1";
        $params = [];

        if ($role === 'student') {
            $sql .= ' AND s.submitter_user_id = :uid';
            $params['uid'] = $user['id'];
        } elseif ($role === 'research_adviser') {
            $sql .= ' AND (s.adviser_user_id = :uid OR EXISTS (
                SELECT 1 FROM review_assignments ra WHERE ra.study_id = s.id AND ra.reviewer_user_id = :uid2
            ))';
            $params['uid'] = $user['id'];
            $params['uid2'] = $user['id'];
        } elseif ($role === 'dept_research_coordinator') {
            $sql .= " AND s.status IN ('pending','dean_review','under_review','library_review','admin_review','approved','archived')";
            if ((int) ($user['program_id'] ?? 0) > 0) {
                $sql .= ' AND s.program_id = :pid';
                $params['pid'] = (int) $user['program_id'];
            }
        } elseif ($role === 'dean') {
            $sql .= " AND s.status IN ('dean_review','under_review','library_review','admin_review','approved','archived')";
            if ((int) ($user['program_id'] ?? 0) > 0) {
                $sql .= ' AND s.program_id = :pid';
                $params['pid'] = (int) $user['program_id'];
            }
        } elseif ($role === 'librarian') {
            // All departments: library queue plus published catalog.
            $sql .= " AND s.status IN ('library_review','admin_review','approved','archived')";
        }

        if (!empty($filters['status'])) {
            $sql .= ' AND s.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY CASE WHEN s.status = 'under_review' THEN 0 ELSE 1 END, s.updated_at DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function listSubmittedBy(int $userId): array
    {
        $stmt = db()->prepare(
            "SELECT s.*, p.code AS program_code FROM studies s
             INNER JOIN programs p ON p.id = s.program_id
             WHERE s.submitter_user_id = :uid
             ORDER BY CASE WHEN s.status = 'under_review' THEN 0 ELSE 1 END, s.updated_at DESC"
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function canAccess(array $user, array $study): bool
    {
        $role = $user['role_slug'];
        if (in_array($role, ['school_administrator', 'school_research_coordinator', 'librarian'], true)) {
            return true;
        }
        if ($role === 'student' && (int) $study['submitter_user_id'] === (int) $user['id']) {
            return true;
        }
        if ($role === 'research_adviser') {
            if ((int) $study['submitter_user_id'] === (int) $user['id'] || (int) $study['adviser_user_id'] === (int) $user['id']) {
                return true;
            }
            $stmt = db()->prepare('SELECT 1 FROM review_assignments WHERE study_id = :sid AND reviewer_user_id = :uid');
            $stmt->execute(['sid' => $study['id'], 'uid' => $user['id']]);
            return (bool) $stmt->fetchColumn();
        }
        if ($role === 'dept_research_coordinator') {
            // Full access to studies from students in their department (any pipeline stage).
            return \App\Helpers\Role::sameDepartment($user, $study);
        }
        if ($role === 'dean') {
            // Full access to every study in their department (any pipeline stage).
            return \App\Helpers\Role::sameDepartment($user, $study);
        }
        return false;
    }

    /**
     * School Research Coordinator, Librarian, School Administrator: all papers, all departments.
     * Research adviser: full manuscript for their department, for assigned advisees, and for papers they uploaded.
     * Dean / dept research coordinator: full manuscript for their department; other departments title/abstract only.
     * Students: full file for studies uploaded in their own department. Other departments are title and abstract only.
     */
    public static function canOpenFullManuscript(?array $user, array $study): bool
    {
        if (!$user) {
            return false;
        }
        $role = $user['role_slug'] ?? '';
        if (\App\Helpers\Role::canViewAllManuscripts($role)) {
            return true;
        }

        // Students download the full file only for studies in their own department.
        if ($role === 'student') {
            if ((int) ($study['submitter_user_id'] ?? 0) === (int) ($user['id'] ?? 0)) {
                return true;
            }
            return \App\Helpers\Role::sameDepartment($user, $study);
        }

        // Advisers get the whole file for their department, for students they advise, and for papers they uploaded.
        if ($role === 'research_adviser') {
            if (\App\Helpers\Role::sameDepartment($user, $study)) {
                return true;
            }
            if ((int) ($study['adviser_user_id'] ?? 0) === (int) $user['id']) {
                return true;
            }
            if ((int) ($study['submitter_user_id'] ?? 0) === (int) $user['id']) {
                return true;
            }
            if (($study['status'] ?? '') !== 'approved') {
                return self::canAccess($user, $study);
            }
            return false;
        }

        // Dean and dept research coordinator: whole paper for their department.
        if (in_array($role, ['dean', 'dept_research_coordinator'], true)) {
            return \App\Helpers\Role::sameDepartment($user, $study);
        }

        if (($study['status'] ?? '') !== 'approved') {
            return self::canAccess($user, $study);
        }
        return true;
    }

    public static function canReplaceManuscript(?array $user, array $study): bool
    {
        if (!$user || $study === []) {
            return false;
        }
        $role = \App\Helpers\Role::canonical((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
        if ($role === \App\Helpers\Role::SCHOOL_ADMINISTRATOR) {
            return true;
        }
        return $role === \App\Helpers\Role::STUDENT
            && (int) ($study['submitter_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
    }

    /** Approved studies in one department (student catalog). */
    /** @return list<array<string,mixed>> */
    public static function listApprovedInProgram(int $programId): array
    {
        if ($programId <= 0) {
            return [];
        }
        $stmt = db()->prepare(
            "SELECT s.id, s.title, s.short_title, s.status, s.abstract, s.research_objectives,
                    s.school_year, s.study_type, s.program_id, s.current_version_id, s.submitter_user_id,
                    s.approved_at, s.submitted_at, s.created_at, p.code AS program_code,
                    (
                        SELECT GROUP_CONCAT(
                            CONCAT(a.last_name, ', ', a.first_name,
                                IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                            ORDER BY a.sort_order SEPARATOR '; '
                        )
                        FROM study_authors a
                        WHERE a.study_id = s.id
                    ) AS authors
             FROM studies s
             INNER JOIN programs p ON p.id = s.program_id
             WHERE s.status = 'approved' AND s.program_id = :pid
             ORDER BY s.approved_at DESC, s.title ASC"
        );
        $stmt->execute(['pid' => $programId]);
        return $stmt->fetchAll();
    }

    /** Approved studies from every department. The student's own department is listed first. */
    /** @return list<array<string,mixed>> */
    public static function listApprovedCatalog(int $homeProgramId = 0): array
    {
        $stmt = db()->prepare(
            "SELECT s.id, s.title, s.short_title, s.status, s.abstract, s.research_objectives,
                    s.school_year, s.study_type, s.program_id, s.current_version_id, s.submitter_user_id,
                    s.approved_at, s.submitted_at, s.created_at, p.code AS program_code,
                    (
                        SELECT GROUP_CONCAT(
                            CONCAT(a.last_name, ', ', a.first_name,
                                IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                            ORDER BY a.sort_order SEPARATOR '; '
                        )
                        FROM study_authors a
                        WHERE a.study_id = s.id
                    ) AS authors
             FROM studies s
             INNER JOIN programs p ON p.id = s.program_id
             WHERE s.program_id = :home_pid
             ORDER BY CASE WHEN s.program_id = :home_pid_case THEN 0 ELSE 1 END, p.code ASC, s.updated_at DESC, s.title ASC"
        );
        $stmt->execute([
            'home_pid' => $homeProgramId,
            'home_pid_case' => $homeProgramId,
        ]);
        return $stmt->fetchAll();
    }

    /** All studies school-wide (school research coordinator / school admin catalog). */
    /** @return list<array<string,mixed>> */
    public static function listAllSchoolWide(): array
    {
        $stmt = db()->query(
            "SELECT s.*, p.code AS program_code,
                    u.first_name AS submitter_first, u.last_name AS submitter_last,
                    (
                        SELECT GROUP_CONCAT(
                            CONCAT(a.last_name, ', ', a.first_name,
                                IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                            ORDER BY a.sort_order SEPARATOR '; '
                        )
                        FROM study_authors a
                        WHERE a.study_id = s.id
                    ) AS authors
             FROM studies s
             INNER JOIN programs p ON p.id = s.program_id
             INNER JOIN users u ON u.id = s.submitter_user_id
             ORDER BY CASE WHEN s.status = 'under_review' THEN 0 ELSE 1 END, p.code ASC, s.updated_at DESC, s.title ASC"
        );
        return $stmt->fetchAll();
    }

    /** All studies in a department (dept coordinator catalog — full files). */
    /** @return list<array<string,mixed>> */
    public static function listAllInProgram(int $programId): array
    {
        if ($programId <= 0) {
            return [];
        }
        $stmt = db()->prepare(
            "SELECT s.*, p.code AS program_code,
                    u.first_name AS submitter_first, u.last_name AS submitter_last,
                    (
                        SELECT GROUP_CONCAT(
                            CONCAT(a.last_name, ', ', a.first_name,
                                IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                            ORDER BY a.sort_order SEPARATOR '; '
                        )
                        FROM study_authors a
                        WHERE a.study_id = s.id
                    ) AS authors
             FROM studies s
             INNER JOIN programs p ON p.id = s.program_id
             INNER JOIN users u ON u.id = s.submitter_user_id
             WHERE s.program_id = :pid
             ORDER BY
                FIELD(s.status, 'under_review', 'pending', 'submitted', 'revision_requested', 'resubmitted', 'adviser_reviewed',
                      'dean_review', 'library_review', 'admin_review', 'approved', 'draft', 'archived'),
                s.updated_at DESC"
        );
        $stmt->execute(['pid' => $programId]);
        return $stmt->fetchAll();
    }

    /** Studies assigned to this research adviser (full-file access). */
    /** @return list<array<string,mixed>> */
    public static function listAssignedToAdviser(int $adviserId): array
    {
        if ($adviserId <= 0) {
            return [];
        }
        $stmt = db()->prepare(
            "SELECT s.*, p.code AS program_code,
                    u.first_name AS submitter_first, u.last_name AS submitter_last,
                    (
                        SELECT GROUP_CONCAT(
                            CONCAT(a.last_name, ', ', a.first_name,
                                IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                            ORDER BY a.sort_order SEPARATOR '; '
                        )
                        FROM study_authors a
                        WHERE a.study_id = s.id
                    ) AS authors
             FROM studies s
             INNER JOIN programs p ON p.id = s.program_id
             INNER JOIN users u ON u.id = s.submitter_user_id
             WHERE s.adviser_user_id = :uid
                OR EXISTS (
                    SELECT 1 FROM review_assignments ra
                    WHERE ra.study_id = s.id AND ra.reviewer_user_id = :uid2
                )
             ORDER BY
                FIELD(s.status, 'under_review', 'submitted', 'revision_requested', 'resubmitted', 'adviser_reviewed', 'pending',
                      'dean_review', 'library_review', 'admin_review', 'approved', 'draft', 'archived'),
                s.updated_at DESC"
        );
        $stmt->execute(['uid' => $adviserId, 'uid2' => $adviserId]);
        return $stmt->fetchAll();
    }

    /**
     * Approved papers outside the adviser's department (title + abstract only).
     * Excludes papers they advise so those stay in the assigned list with full files.
     *
     * @return list<array<string,mixed>>
     */
    public static function listApprovedOutsideProgram(int $programId, int $adviserId = 0): array
    {
        $sql = "SELECT s.id, s.title, s.short_title, s.status, s.abstract, s.research_objectives,
                       s.school_year, s.study_type, s.program_id, s.current_version_id, s.submitter_user_id, s.adviser_user_id,
                       s.approved_at, s.submitted_at, s.created_at, p.code AS program_code,
                       (
                           SELECT GROUP_CONCAT(
                               CONCAT(a.last_name, ', ', a.first_name,
                                   IF(a.middle_name IS NULL OR a.middle_name = '', '', CONCAT(' ', LEFT(a.middle_name, 1), '.')))
                               ORDER BY a.sort_order SEPARATOR '; '
                           )
                           FROM study_authors a
                           WHERE a.study_id = s.id
                       ) AS authors
                FROM studies s
                INNER JOIN programs p ON p.id = s.program_id
                WHERE s.status = 'approved'";
        $params = [];
        if ($programId > 0) {
            $sql .= ' AND s.program_id <> :pid';
            $params['pid'] = $programId;
        }
        if ($adviserId > 0) {
            $sql .= ' AND (s.adviser_user_id IS NULL OR s.adviser_user_id <> :aid)';
            $params['aid'] = $adviserId;
        }
        $sql .= ' ORDER BY p.code ASC, s.approved_at DESC, s.title ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function createDraft(int $submitterId, array $meta, array $authors, array $keywords): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO studies (submitter_user_id, title, short_title, abstract, research_objectives, study_type, program_id, adviser_user_id, pass_to,
                 school_year, defense_date, coi_statement, status)
                 VALUES (:sub, :title, :st, :abs, :obj, :type, :pid, :adv, :pass, :sy, :dd, :coi, :status)'
            );
            $stmt->execute([
                'sub' => $submitterId,
                'title' => $meta['title'],
                'st' => $meta['short_title'],
                'abs' => $meta['abstract'],
                'obj' => $meta['research_objectives'],
                'type' => $meta['study_type'],
                'pid' => $meta['program_id'],
                'adv' => $meta['adviser_user_id'] ?: null,
                'pass' => $meta['pass_to'],
                'sy' => $meta['school_year'],
                'dd' => $meta['defense_date'] ?: null,
                'coi' => $meta['coi_statement'],
                'status' => 'draft',
            ]);
            $studyId = (int) $pdo->lastInsertId();
            self::saveAuthors($studyId, $authors);
            self::saveKeywords($studyId, $keywords);
            $pdo->prepare('INSERT INTO ethics_checklists (study_id) VALUES (:id)')->execute(['id' => $studyId]);
            $pdo->commit();
            return $studyId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array{name:string}|null $blindFile
     */
    public static function addVersion(
        int $studyId,
        int $uploaderId,
        array $file,
        string $storedPath,
        string $mime,
        int $size,
        ?string $note,
        ?array $blindFile = null,
        ?string $blindPath = null,
        ?string $blindMime = null,
        ?int $blindSize = null
    ): int {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version_number), 0) + 1 AS next FROM study_versions WHERE study_id = :id');
        $stmt->execute(['id' => $studyId]);
        $next = (int) $stmt->fetchColumn();

        $ins = $pdo->prepare(
            'INSERT INTO study_versions (study_id, version_number, stored_path, original_filename, mime_type, file_size,
             blind_stored_path, blind_original_filename, blind_mime_type, blind_file_size, change_note, uploaded_by)
             VALUES (:sid, :vn, :path, :orig, :mime, :size, :bpath, :borig, :bmime, :bsize, :note, :uid)'
        );
        $ins->execute([
            'sid' => $studyId,
            'vn' => $next,
            'path' => $storedPath,
            'orig' => $file['name'],
            'mime' => $mime,
            'size' => $size,
            'bpath' => $blindPath,
            'borig' => $blindFile['name'] ?? null,
            'bmime' => $blindMime,
            'bsize' => $blindSize,
            'note' => $note,
            'uid' => $uploaderId,
        ]);
        $versionId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE studies SET current_version_id = :vid, updated_at = NOW() WHERE id = :sid')
            ->execute(['vid' => $versionId, 'sid' => $studyId]);
        return $versionId;
    }

    public static function passToLibrary(int $studyId): bool
    {
        $stmt = db()->prepare(
            "UPDATE studies SET status = 'pending', submitted_at = COALESCE(submitted_at, NOW()), updated_at = NOW()
             WHERE id = :id AND status IN ('draft','revision_requested')"
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->rowCount() > 0;
    }

    public static function submit(int $studyId): void
    {
        db()->prepare(
            "UPDATE studies SET status = 'submitted', submitted_at = COALESCE(submitted_at, NOW()), updated_at = NOW()
             WHERE id = :id AND status IN ('draft','revision_requested')"
        )->execute(['id' => $studyId]);
    }

    public static function resubmit(int $studyId): void
    {
        db()->prepare(
            "UPDATE studies SET status = 'submitted', submitted_at = COALESCE(submitted_at, NOW()), updated_at = NOW()
             WHERE id = :id AND status = 'revision_requested'"
        )->execute(['id' => $studyId]);
    }

    public static function markPending(int $studyId): void
    {
        db()->prepare(
            "UPDATE studies SET status = 'pending', updated_at = NOW()
             WHERE id = :id AND status IN ('submitted','ethics_review','resubmitted')"
        )->execute(['id' => $studyId]);
    }

    public static function markAdviserReviewed(int $studyId): bool
    {
        $stmt = db()->prepare(
            "UPDATE studies SET status = 'adviser_reviewed', updated_at = NOW()
             WHERE id = :id AND status = 'submitted'"
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->rowCount() > 0;
    }

    public static function sendToLibrarian(int $studyId): bool
    {
        $stmt = db()->prepare(
            "UPDATE studies SET status = 'pending', updated_at = NOW()
             WHERE id = :id AND status = 'adviser_reviewed'"
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->rowCount() > 0;
    }

    public static function sendToDean(int $studyId): bool
    {
        $stmt = db()->prepare(
            "UPDATE studies SET status = 'dean_review', updated_at = NOW()
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->rowCount() > 0;
    }

    /** @deprecated Use sendToDean() */
    public static function sendToDirector(int $studyId): bool
    {
        return self::sendToDean($studyId);
    }

    public static function sendToSchoolCoordinator(int $studyId): bool
    {
        $stmt = db()->prepare(
            "UPDATE studies SET status = 'under_review', updated_at = NOW()
             WHERE id = :id AND status = 'dean_review'"
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->rowCount() > 0;
    }

    public static function sendToLibrarianGate(int $studyId): bool
    {
        return self::sendToSchoolAdminFromCoordinator($studyId);
    }

    public static function sendToSchoolAdminFromCoordinator(int $studyId): bool
    {
        $stmt = db()->prepare(
            "UPDATE studies SET status = 'admin_review', updated_at = NOW()
             WHERE id = :id AND status IN ('under_review', 'library_review')"
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->rowCount() > 0;
    }

    public static function sendToSchoolAdmin(int $studyId): bool
    {
        $stmt = db()->prepare(
            "UPDATE studies SET status = 'admin_review', updated_at = NOW()
             WHERE id = :id AND status IN ('library_review', 'under_review')"
        );
        $stmt->execute(['id' => $studyId]);
        return $stmt->rowCount() > 0;
    }

    public static function adviserHasReviewed(int $studyId, int $userId): bool
    {
        $stmt = db()->prepare(
            'SELECT 1 FROM review_comments WHERE study_id = :sid AND reviewer_user_id = :uid
             UNION
             SELECT 1 FROM review_scores WHERE study_id = :sid2 AND reviewer_user_id = :uid2
             LIMIT 1'
        );
        $stmt->execute([
            'sid' => $studyId,
            'uid' => $userId,
            'sid2' => $studyId,
            'uid2' => $userId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hasLibraryReview(int $studyId): bool
    {
        $stmt = db()->prepare(
            "SELECT 1 FROM review_comments
             WHERE study_id = :sid
               AND section_label IN (
                    'Library review',
                    'Librarian review',
                    'Department research coordinator review'
               )
             LIMIT 1"
        );
        $stmt->execute(['sid' => $studyId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $meta */
    public static function updateDetails(int $studyId, array $meta, array $authors, array $keywords): void
    {
        $stmt = db()->prepare(
            'UPDATE studies SET title = :title, short_title = :st, abstract = :abs, research_objectives = :obj,
             study_type = :type, program_id = :pid, adviser_user_id = :adv, pass_to = :pass, school_year = :sy, defense_date = :dd,
             coi_statement = :coi, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => $meta['title'],
            'st' => $meta['short_title'],
            'abs' => $meta['abstract'],
            'obj' => $meta['research_objectives'],
            'type' => $meta['study_type'],
            'pid' => $meta['program_id'],
            'adv' => $meta['adviser_user_id'] ?: null,
            'pass' => $meta['pass_to'],
            'sy' => $meta['school_year'],
            'dd' => $meta['defense_date'] ?: null,
            'coi' => $meta['coi_statement'],
            'id' => $studyId,
        ]);
        self::saveAuthors($studyId, $authors);
        self::saveKeywords($studyId, $keywords);
    }

    public static function deleteOwned(int $studyId): void
    {
        $versions = self::versions($studyId);
        db()->prepare('UPDATE studies SET current_version_id = NULL WHERE id = :id')->execute(['id' => $studyId]);
        foreach ($versions as $version) {
            foreach (['stored_path', 'blind_stored_path'] as $key) {
                $rel = $version[$key] ?? '';
                if ($rel !== '') {
                    \App\Helpers\StorageFiles::delete((string) $rel);
                }
            }
        }
        db()->prepare('DELETE FROM studies WHERE id = :id')->execute(['id' => $studyId]);
    }

    /** @param list<array{first_name:string,middle_name?:string,last_name:string}> $authors */
    public static function saveAuthors(int $studyId, array $authors): void
    {
        db()->prepare('DELETE FROM study_authors WHERE study_id = :id')->execute(['id' => $studyId]);
        $stmt = db()->prepare(
            'INSERT INTO study_authors (study_id, sort_order, first_name, middle_name, last_name) VALUES (:sid, :ord, :fn, :mn, :ln)'
        );
        foreach ($authors as $i => $a) {
            $stmt->execute([
                'sid' => $studyId,
                'ord' => $i + 1,
                'fn' => $a['first_name'],
                'mn' => $a['middle_name'] ?? null,
                'ln' => $a['last_name'],
            ]);
        }
    }

    /** @param list<string> $keywords */
    public static function saveKeywords(int $studyId, array $keywords): void
    {
        db()->prepare('DELETE FROM study_keywords WHERE study_id = :id')->execute(['id' => $studyId]);
        $stmt = db()->prepare('INSERT INTO study_keywords (study_id, keyword) VALUES (:sid, :kw)');
        foreach ($keywords as $kw) {
            $stmt->execute(['sid' => $studyId, 'kw' => $kw]);
        }
    }

    public static function updateCatalog(int $studyId, string $abstract, string $studyType, string $schoolYear): void
    {
        db()->prepare(
            'UPDATE studies SET abstract = :abs, study_type = :type, school_year = :sy, updated_at = NOW() WHERE id = :id'
        )->execute([
            'abs' => $abstract,
            'type' => $studyType,
            'sy' => $schoolYear,
            'id' => $studyId,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function downloadHistory(int $userId): array
    {
        $stmt = db()->prepare(
            "SELECT a.created_at, a.entity_id AS study_id, a.details, s.title
             FROM audit_logs a
             LEFT JOIN studies s ON s.id = a.entity_id
             WHERE a.user_id = :u AND a.action = 'study.download'
               AND a.id = (
                 SELECT MAX(latest.id) FROM audit_logs latest
                 WHERE latest.user_id = :u2 AND latest.action = 'study.download' AND latest.entity_id = a.entity_id
               )
             ORDER BY a.created_at DESC
             LIMIT 40"
        );
        $stmt->execute(['u' => $userId, 'u2' => $userId]);
        return $stmt->fetchAll();
    }

    public static function ethicsIsComplete(array $ethics): bool
    {
        $fields = ['human_subjects', 'informed_consent', 'ethical_clearance', 'data_privacy_ra10173', 'coi_disclosed'];
        foreach ($fields as $f) {
            if (($ethics[$f] ?? 'pending') !== 'passed') {
                return false;
            }
        }
        return true;
    }

    public static function canApprove(int $studyId): array
    {
        $study = self::find($studyId);
        if (!$study) {
            return ['ok' => false, 'reason' => 'Study not found.'];
        }
        if ($study['status'] !== 'under_review') {
            return ['ok' => false, 'reason' => 'Clear adviser, department, dean, then complete school research checks first.'];
        }
        return ['ok' => true, 'reason' => ''];
    }

    /** School research coordinator sends the study to the school administrator (not final publish). */
    public static function approve(int $studyId): bool
    {
        $check = self::canApprove($studyId);
        if (!$check['ok']) {
            return false;
        }
        return self::sendToSchoolAdminFromCoordinator($studyId);
    }

    public static function canFinalPublish(int $studyId): array
    {
        $study = self::find($studyId);
        if (!$study) {
            return ['ok' => false, 'reason' => 'Study not found.'];
        }
        if (!in_array((string) $study['status'], ['admin_review', 'library_review'], true)) {
            return ['ok' => false, 'reason' => 'The school research coordinator must clear this study first.'];
        }
        return ['ok' => true, 'reason' => ''];
    }

    public static function finalPublish(int $studyId): bool
    {
        $check = self::canFinalPublish($studyId);
        if (!$check['ok']) {
            return false;
        }
        $updated = db()->prepare(
            "UPDATE studies SET status = 'approved', approved_at = NOW(), updated_at = NOW()
             WHERE id = :id AND status IN ('admin_review', 'library_review')"
        );
        $updated->execute(['id' => $studyId]);
        if ($updated->rowCount() < 1) {
            return false;
        }
        $study = self::find($studyId);
        if ($study) {
            \App\Helpers\SupabaseSync::syncStudyFromRow($study);
        }
        return true;
    }

    /** @param array<string,mixed> $filters */
    public static function searchApproved(array $filters, string $sort = 'relevance'): array
    {
        $q = trim($filters['q'] ?? '');
        $params = [];
        $sql = "SELECT s.*, p.code AS program_code,
                       COALESCE(rt.star_total, 0) AS star_total,
                       COALESCE(rt.star_votes, 0) AS star_votes,
                       COALESCE(rt.star_avg, 0) AS star_avg,
                       cv.original_filename
                FROM studies s
                INNER JOIN programs p ON p.id = s.program_id
                INNER JOIN users sub ON sub.id = s.submitter_user_id
                INNER JOIN roles sr ON sr.id = sub.role_id
                LEFT JOIN (
                    SELECT study_id, SUM(stars) AS star_total, COUNT(*) AS star_votes, AVG(stars) AS star_avg
                    FROM study_ratings
                    GROUP BY study_id
                ) rt ON rt.study_id = s.id
                LEFT JOIN study_versions cv ON cv.id = s.current_version_id
                WHERE s.status = 'approved' AND sr.slug IN ('student', 'research_adviser')";

        if ($q !== '') {
            $words = self::searchWords($q);
            $hay = "CONCAT_WS(' ', s.title, s.short_title, IFNULL(s.abstract, ''), IFNULL(s.research_objectives, ''),
                IFNULL((SELECT GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ' ')
                        FROM study_authors a WHERE a.study_id = s.id), ''),
                IFNULL((SELECT GROUP_CONCAT(k.keyword SEPARATOR ' ')
                        FROM study_keywords k WHERE k.study_id = s.id), '')
            )";
            $parts = [];
            foreach ($words as $i => $word) {
                $key = 'w' . $i;
                $parts[] = $hay . " LIKE :{$key}";
                $params[$key] = '%' . self::likeEscape($word) . '%';
            }
            $sql .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        if (!empty($filters['program_id'])) {
            $sql .= ' AND s.program_id = :pid';
            $params['pid'] = $filters['program_id'];
        }
        if (!empty($filters['school_year'])) {
            $sql .= ' AND s.school_year = :sy';
            $params['sy'] = $filters['school_year'];
        }
        if (!empty($filters['adviser_user_id'])) {
            $sql .= ' AND s.adviser_user_id = :aid';
            $params['aid'] = $filters['adviser_user_id'];
        }
        if (!empty($filters['study_type'])) {
            $sql .= ' AND s.study_type = :st';
            $params['st'] = $filters['study_type'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM study_keywords k WHERE k.study_id = s.id AND k.keyword = :kw)';
            $params['kw'] = strtolower($filters['keyword']);
        }
        if (!empty($filters['date_field']) && !empty($filters['date_from'])) {
            $col = match ($filters['date_field']) {
                'approval' => 's.approved_at',
                'defense' => 's.defense_date',
                default => 's.submitted_at',
            };
            $sql .= " AND {$col} >= :df";
            $params['df'] = $filters['date_from'];
        }
        if (!empty($filters['date_field']) && !empty($filters['date_to'])) {
            $col = match ($filters['date_field']) {
                'approval' => 's.approved_at',
                'defense' => 's.defense_date',
                default => 's.submitted_at',
            };
            $sql .= " AND {$col} <= :dt";
            $params['dt'] = $filters['date_to'] . ' 23:59:59';
        }

        $browsing = $q === ''
            && ($filters['school_year'] ?? '') === ''
            && ($filters['adviser_user_id'] ?? '') === ''
            && ($filters['study_type'] ?? '') === ''
            && ($filters['keyword'] ?? '') === ''
            && ($filters['date_from'] ?? '') === ''
            && ($filters['date_to'] ?? '') === '';
        // Guests/staff with no department filter: show a short featured list.
        // Students locked to a department: show all approved papers in that department.
        $featuredLimit = ($filters['program_id'] ?? '') === '';
        if ($browsing && $featuredLimit) {
            $sql .= ' ORDER BY star_total DESC, star_votes DESC, s.approved_at DESC, s.title ASC LIMIT 3';
        } elseif ($browsing) {
            $sql .= ' ORDER BY star_total DESC, star_votes DESC, s.approved_at DESC, s.title ASC';
        } else {
            $sql .= match ($sort) {
                'title' => ' ORDER BY s.title ASC',
                'date' => ' ORDER BY s.approved_at DESC, s.title ASC',
                default => ' ORDER BY star_total DESC, s.approved_at DESC, s.title ASC',
            };
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function ratingSummary(int $studyId): array
    {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(stars), 0) AS star_total,
                    COUNT(*) AS star_votes,
                    COALESCE(AVG(stars), 0) AS star_avg
             FROM study_ratings WHERE study_id = :id'
        );
        $stmt->execute(['id' => $studyId]);
        $row = $stmt->fetch() ?: ['star_total' => 0, 'star_votes' => 0, 'star_avg' => 0];
        return [
            'star_total' => (int) $row['star_total'],
            'star_votes' => (int) $row['star_votes'],
            'star_avg' => (float) $row['star_avg'],
        ];
    }

    /** @param list<int> $studyIds @return array<int, int> */
    public static function userRatingsMap(int $userId, array $studyIds): array
    {
        $studyIds = array_values(array_unique(array_map('intval', $studyIds)));
        if ($userId < 1 || $studyIds === []) {
            return [];
        }
        $in = implode(',', $studyIds);
        $stmt = db()->prepare("SELECT study_id, stars FROM study_ratings WHERE user_id = :u AND study_id IN ({$in})");
        $stmt->execute(['u' => $userId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['study_id']] = (int) $row['stars'];
        }
        return $map;
    }

    public static function userRating(int $studyId, int $userId): int
    {
        $stmt = db()->prepare('SELECT stars FROM study_ratings WHERE study_id = :s AND user_id = :u');
        $stmt->execute(['s' => $studyId, 'u' => $userId]);
        $stars = $stmt->fetchColumn();
        return $stars === false ? 0 : (int) $stars;
    }

    public static function rate(int $studyId, int $userId, int $stars): void
    {
        $stmt = db()->prepare(
            'INSERT INTO study_ratings (study_id, user_id, stars)
             VALUES (:s, :u, :stars)
             ON DUPLICATE KEY UPDATE stars = VALUES(stars), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute(['s' => $studyId, 'u' => $userId, 'stars' => $stars]);
    }

    public static function suggest(string $term, int $limit = 10, int $programId = 0): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = $term . '%';
        $deptSql = $programId > 0 ? ' AND s.program_id = :pid' : '';
        $titles = db()->prepare(
            "SELECT DISTINCT s.title AS label, 'title' AS type FROM studies s
             WHERE s.status = 'approved' AND s.title LIKE :t{$deptSql} LIMIT :lim"
        );
        $titles->bindValue(':t', $like);
        if ($programId > 0) {
            $titles->bindValue(':pid', $programId, PDO::PARAM_INT);
        }
        $titles->bindValue(':lim', $limit, PDO::PARAM_INT);
        $titles->execute();
        $authors = db()->prepare(
            "SELECT DISTINCT CONCAT(a.last_name, ', ', a.first_name) AS label, 'author' AS type
             FROM study_authors a INNER JOIN studies s ON s.id = a.study_id
             WHERE s.status = 'approved' AND a.last_name LIKE :t{$deptSql} LIMIT :lim"
        );
        $authors->bindValue(':t', $like);
        if ($programId > 0) {
            $authors->bindValue(':pid', $programId, PDO::PARAM_INT);
        }
        $authors->bindValue(':lim', $limit, PDO::PARAM_INT);
        $authors->execute();
        $kw = db()->prepare(
            "SELECT DISTINCT k.keyword AS label, 'keyword' AS type FROM study_keywords k
             INNER JOIN studies s ON s.id = k.study_id
             WHERE s.status = 'approved' AND k.keyword LIKE :t{$deptSql} LIMIT :lim"
        );
        $kw->bindValue(':t', $like);
        if ($programId > 0) {
            $kw->bindValue(':pid', $programId, PDO::PARAM_INT);
        }
        $kw->bindValue(':lim', $limit, PDO::PARAM_INT);
        $kw->execute();
        return array_slice(array_merge($titles->fetchAll(), $authors->fetchAll(), $kw->fetchAll()), 0, $limit);
    }

    /** @return list<string> */
    private static function searchWords(string $q): array
    {
        $skip = ['a', 'an', 'and', 'for', 'in', 'of', 'on', 'or', 'the', 'to'];
        $parts = preg_split('/\s+/u', $q) ?: [];
        $words = [];
        foreach ($parts as $part) {
            $word = trim($part);
            if ($word === '' || mb_strlen($word) < 3 || in_array(mb_strtolower($word), $skip, true)) {
                continue;
            }
            $words[] = $word;
        }
        return $words !== [] ? array_values(array_unique($words)) : [$q];
    }

    private static function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function fulltextQuery(string $q): string
    {
        $parts = preg_split('/\s+/', $q) ?: [];
        $parts = array_filter($parts, static fn ($p) => $p !== '');
        return implode(' ', array_map(static fn ($p) => '+' . preg_replace('/[^\p{L}\p{N}]+/u', '', $p), $parts));
    }

    /** @return list<string> School years such as 2025-2026, newest first. */
    public static function schoolYearOptions(): array
    {
        $years = [];
        $start = (int) date('Y') - 8;
        $end = (int) date('Y') + 1;
        for ($y = $end; $y >= $start; $y--) {
            $years[] = $y . '-' . ($y + 1);
        }
        try {
            $rows = db()->query('SELECT DISTINCT school_year FROM studies WHERE school_year <> \'\' ORDER BY school_year DESC')->fetchAll();
            foreach ($rows as $row) {
                $years[] = (string) $row['school_year'];
            }
        } catch (\Throwable) {
        }
        $years = array_values(array_unique($years));
        rsort($years, SORT_STRING);
        return $years;
    }

    public static function formatAuthorList(int $studyId, bool $hideNames = false): string
    {
        if ($hideNames) {
            return 'Authors withheld (double-blind review)';
        }
        $authors = self::authors($studyId);
        $parts = [];
        foreach ($authors as $a) {
            $name = $a['last_name'] . ', ' . $a['first_name'];
            if ($a['middle_name']) {
                $name .= ' ' . mb_substr($a['middle_name'], 0, 1) . '.';
            }
            $parts[] = $name;
        }
        return implode('; ', $parts);
    }

    /** @return list<int> */
    public static function savedIds(int $userId): array
    {
        $stmt = db()->prepare('SELECT study_id FROM study_bookmarks WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'study_id'));
    }

    public static function isSaved(int $userId, int $studyId): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM study_bookmarks WHERE user_id = :u AND study_id = :s');
        $stmt->execute(['u' => $userId, 's' => $studyId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function toggleBookmark(int $userId, int $studyId): bool
    {
        if (self::isSaved($userId, $studyId)) {
            $stmt = db()->prepare('DELETE FROM study_bookmarks WHERE user_id = :u AND study_id = :s');
            $stmt->execute(['u' => $userId, 's' => $studyId]);
            return false;
        }
        $stmt = db()->prepare('INSERT INTO study_bookmarks (study_id, user_id) VALUES (:s, :u)');
        $stmt->execute(['s' => $studyId, 'u' => $userId]);
        return true;
    }

    /** @return list<array<string,mixed>> */
    public static function bookmarksFor(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT s.id, s.title, s.short_title, s.school_year, s.status, s.program_id, s.current_version_id,
                    s.adviser_user_id, s.submitter_user_id, p.code AS program_code, b.created_at
             FROM study_bookmarks b
             INNER JOIN studies s ON s.id = b.study_id
             INNER JOIN programs p ON p.id = s.program_id
             WHERE b.user_id = :u AND s.status = \'approved\'
             ORDER BY b.created_at DESC'
        );
        $stmt->execute(['u' => $userId]);
        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $study @param list<array<string,mixed>> $authors @return array<string,string> */
    public static function citations(array $study, array $authors): array
    {
        $year = self::citeYear($study);
        $title = trim((string) ($study['title'] ?? ''));
        $venue = 'Lanao School of Science and Technology';
        if (!empty($study['program_code'])) {
            $venue = $study['program_code'] . ', ' . $venue;
        }
        $bibAuthors = [];
        foreach ($authors as $author) {
            $bibAuthors[] = trim($author['last_name'] . ', ' . $author['first_name']);
        }
        $key = preg_replace('/[^A-Za-z0-9]/', '', (string) ($authors[0]['last_name'] ?? 'study')) . $year;
        return [
            'MLA' => self::joinNames($authors, 'mla') . ' "' . $title . '." ' . $venue . ', ' . $year . '.',
            'APA' => self::joinNames($authors, 'apa') . ' (' . $year . '). ' . $title . '. ' . $venue . '.',
            'Chicago' => self::joinNames($authors, 'chicago') . ' "' . $title . '." ' . $venue . ', ' . $year . '.',
            'Harvard' => self::joinNames($authors, 'harvard') . ' (' . $year . ') ' . $title . '. ' . $venue . '.',
            'Vancouver' => rtrim(self::joinNames($authors, 'vancouver'), '.') . '. ' . $title . '. ' . $venue . '; ' . $year . '.',
            'BibTeX' => "@thesis{{$key},\n  author = {" . implode(' and ', $bibAuthors) . "},\n  title = {{$title}},\n  school = {Lanao School of Science and Technology},\n  year = {{$year}}\n}",
        ];
    }

    /** @param array<string,mixed> $study */
    private static function citeYear(array $study): string
    {
        if (preg_match_all('/\d{4}/', (string) ($study['school_year'] ?? ''), $matches) && $matches[0] !== []) {
            return (string) end($matches[0]);
        }
        if (!empty($study['approved_at'])) {
            return substr((string) $study['approved_at'], 0, 4);
        }
        return date('Y');
    }

    /** @param list<array<string,mixed>> $authors */
    private static function joinNames(array $authors, string $style): string
    {
        if ($authors === []) {
            return 'Unknown';
        }
        $names = [];
        foreach ($authors as $index => $author) {
            $first = trim((string) ($author['first_name'] ?? ''));
            $last = trim((string) ($author['last_name'] ?? ''));
            $middle = trim((string) ($author['middle_name'] ?? ''));
            $middleInitial = $middle !== '' ? mb_strtoupper(mb_substr($middle, 0, 1)) . '.' : '';
            $firstInitial = $first !== '' ? mb_strtoupper(mb_substr($first, 0, 1)) . '.' : '';
            $inverted = $index === 0;
            $names[] = match ($style) {
                'apa', 'harvard' => trim($last . ', ' . $firstInitial . ($middleInitial !== '' ? ' ' . $middleInitial : '')),
                'vancouver' => trim($last . ' ' . $firstInitial . str_replace('.', '', $middleInitial)),
                default => $inverted
                    ? trim($last . ', ' . $first . ($middleInitial !== '' ? ' ' . $middleInitial : ''))
                    : trim($first . ($middleInitial !== '' ? ' ' . $middleInitial : '') . ' ' . $last),
            };
        }
        $joiner = match ($style) {
            'apa' => ', & ',
            'vancouver' => ', ',
            default => ', and ',
        };
        if (count($names) === 1) {
            return $names[0];
        }
        if (count($names) === 2) {
            $pair = match ($style) {
                'apa' => ' & ',
                'vancouver' => ', ',
                default => ' and ',
            };
            return $names[0] . $pair . $names[1];
        }
        if ($style === 'vancouver') {
            return implode(', ', $names);
        }
        $last = array_pop($names);
        return implode(', ', $names) . $joiner . $last;
    }
}
