<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\Program;
use App\Models\Study;

final class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        if (($user['role_slug'] ?? '') === \App\Helpers\Role::ACCOUNT_ADMIN) {
            redirect('admin/accounts');
        }
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            redirect('password/change');
        }
        $role = $user['role_slug'] ?? '';
        $studies = [];
        $notifications = [];
        $stats = $role === 'student' ? [] : $this->statsForRole($role, (int) $user['id'], (int) ($user['program_id'] ?? 0));
        $uploads = [];
        $success = null;
        $error = null;
        $programs = [];
        $directorStudies = [];
        $deptCatalog = [];
        $otherDeptCatalog = [];
        if ($role === 'student') {
            $deptCatalog = Study::listApprovedCatalog((int) ($user['program_id'] ?? 0), (int) $user['id']);
            $starredIds = array_flip(Study::savedIds((int) $user['id']));
            foreach ($deptCatalog as &$paper) { $paper['is_starred'] = isset($starredIds[(int) $paper['id']]); }
            unset($paper);
            $programs = Program::active();
        }
        if ($role === 'research_adviser') {
            $pid = (int) ($user['program_id'] ?? 0);
            $programs = Program::active();
            $deptCatalog = Study::listAllInProgram($pid);
            $otherDeptCatalog = Study::listApprovedOutsideProgram(
                $pid,
                (int) $user['id']
            );
        }
        if ($role === 'school_research_coordinator') {
            $programs = Program::active();
            $directorStudies = Study::listAllSchoolWide();
            $success = flash('success');
            $error = flash('error');
        }
        if ($role === 'school_administrator') {
            $programs = Program::active();
            $directorStudies = array_values(array_filter(Study::listAllSchoolWide(), static fn (array $study): bool =>
                in_array($study['status'] ?? '', ['admin_review', 'library_review', 'approved'], true)
            ));
            $uploads = array_values(array_filter(
                $directorStudies,
                static fn (array $s): bool => in_array($s['status'] ?? '', ['admin_review', 'library_review'], true)
            ));
            $stats['department_studies'] = count($directorStudies);
            $success = flash('success');
            $error = flash('error');
        }
        if ($role === 'dept_research_coordinator') {
            $pid = (int) ($user['program_id'] ?? 0);
            $programs = Program::active();
            $deptCatalog = Study::listAllInProgram($pid);
            $otherDeptCatalog = Study::listApprovedOutsideProgram($pid);
            // Pending queue for department gate actions (send to dean).
            $uploads = array_values(array_filter(
                $deptCatalog,
                static fn (array $s): bool => ($s['status'] ?? '') === 'pending'
            ));
            $success = flash('success');
            $error = flash('error');
        }
        if ($role === 'dean') {
            $pid = (int) ($user['program_id'] ?? 0);
            $programs = Program::active();
            $deptCatalog = Study::listAllInProgram($pid);
            $otherDeptCatalog = Study::listApprovedOutsideProgram($pid);
            $uploads = array_values(array_filter(
                $deptCatalog,
                static fn (array $s): bool => ($s['status'] ?? '') === 'dean_review'
            ));
            $success = flash('success');
            $error = flash('error');
        }
        if ($role === 'librarian') {
            $programs = Program::active();
            $directorStudies = Study::listAllSchoolWide();
            $uploads = array_values(array_filter(
                $directorStudies,
                static fn (array $s): bool => ($s['status'] ?? '') === 'library_review'
            ));
            $success = flash('success');
            $error = flash('error');
        }
        view('dashboard/index', [
            'user' => $user,
            'studies' => array_slice($studies, 0, 8),
            'uploads' => $uploads,
            'notifications' => $notifications,
            'stats' => $stats,
            'success' => $success,
            'error' => $error,
            'programs' => $programs,
            'directorStudies' => $directorStudies,
            'deptCatalog' => $deptCatalog,
            'otherDeptCatalog' => $otherDeptCatalog,
            'departmentLabel' => trim(($user['program_code'] ?? '') . ' — ' . ($user['program_name'] ?? ''), ' —'),
        ]);
    }

    /** @return array<string, int|string> */
    private function statsForRole(string $role, int $userId, int $programId = 0): array
    {
        if ($role === 'student') {
            $stmt = db()->prepare('SELECT COUNT(*) FROM studies WHERE submitter_user_id = :uid');
            $stmt->execute(['uid' => $userId]);
            return ['my_studies' => (int) $stmt->fetchColumn()];
        }

        [$pending, $accepted] = $this->decisionCounts($role, $userId, $programId);

        return [
            'pending' => $pending,
            'accepted' => $accepted,
            'department_studies' => $this->studyTotal($programId),
            'other_studies' => $this->otherStudyTotal($role, $userId, $programId),
        ];
    }

    /** @return array{0:int,1:int} */
    private function decisionCounts(string $role, int $userId, int $programId): array
    {
        if ($role === 'research_adviser') {
            $stmt = db()->prepare(
                "SELECT
                    SUM(s.status IN ('submitted', 'revision_requested')) AS pending_count,
                    SUM(s.status NOT IN ('submitted', 'revision_requested', 'draft')) AS accepted_count
                 FROM studies s
                 WHERE s.program_id = :pid
                    AND (
                        s.adviser_user_id = :uid
                        OR EXISTS (
                            SELECT 1 FROM review_assignments ra
                            WHERE ra.study_id = s.id AND ra.reviewer_user_id = :uid2
                        )
                    )"
            );
            $stmt->execute(['pid' => $programId, 'uid' => $userId, 'uid2' => $userId]);
            $row = $stmt->fetch() ?: [];
            return [(int) ($row['pending_count'] ?? 0), (int) ($row['accepted_count'] ?? 0)];
        }

        $gates = [
            'dept_research_coordinator' => [
                ['pending'],
                ['dean_review', 'under_review', 'library_review', 'admin_review', 'approved', 'archived'],
            ],
            'dean' => [
                ['dean_review'],
                ['under_review', 'library_review', 'admin_review', 'approved', 'archived'],
            ],
            'school_research_coordinator' => [
                ['under_review'],
                ['library_review', 'admin_review', 'approved', 'archived'],
            ],
            'librarian' => [
                ['library_review'],
                ['admin_review', 'approved', 'archived'],
            ],
            'school_administrator' => [
                ['admin_review', 'library_review'],
                ['approved', 'archived'],
            ],
        ];
        if (!isset($gates[$role])) {
            return [0, 0];
        }
        [$pendingStatuses, $acceptedStatuses] = $gates[$role];
        $departmentOnly = $programId > 0;
        return [
            $this->countStatuses($pendingStatuses, $departmentOnly ? $programId : 0),
            $this->countStatuses($acceptedStatuses, $departmentOnly ? $programId : 0),
        ];
    }

    /** @param list<string> $statuses */
    private function countStatuses(array $statuses, int $programId): int
    {
        if (!$statuses) {
            return 0;
        }
        $marks = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT COUNT(*) FROM studies WHERE status IN ($marks)";
        $params = $statuses;
        if ($programId > 0) {
            $sql .= ' AND program_id = ?';
            $params[] = $programId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function studyTotal(int $programId): int
    {
        if ($programId > 0) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM studies WHERE program_id = :pid');
            $stmt->execute(['pid' => $programId]);
            return (int) $stmt->fetchColumn();
        }
        return (int) db()->query('SELECT COUNT(*) FROM studies')->fetchColumn();
    }

    private function otherStudyTotal(string $role, int $userId, int $programId): int
    {
        if ($programId > 0) {
            $sql = "SELECT COUNT(*) FROM studies WHERE status = 'approved' AND program_id <> :pid";
            $params = ['pid' => $programId];
            if ($role === 'research_adviser') {
                $sql .= ' AND (adviser_user_id IS NULL OR adviser_user_id <> :aid)';
                $params['aid'] = $userId;
            }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }

        $held = match ($role) {
            'school_research_coordinator' => ['under_review', 'library_review', 'admin_review', 'approved', 'archived'],
            'librarian' => ['library_review', 'admin_review', 'approved', 'archived'],
            'school_administrator' => ['admin_review', 'library_review', 'approved', 'archived'],
            default => [],
        };
        if (!$held) {
            return 0;
        }
        $marks = implode(',', array_fill(0, count($held), '?'));
        $stmt = db()->prepare("SELECT COUNT(*) FROM studies WHERE status NOT IN ($marks) AND status <> 'draft'");
        $stmt->execute($held);
        return (int) $stmt->fetchColumn();
    }
}
