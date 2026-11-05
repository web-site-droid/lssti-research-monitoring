<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Settings;
use App\Models\Program;

final class SettingsController
{
    public function index(): void
    {
        $programs = Program::directorAdded();
        $counts = $this->studyCountsByProgram();
        $students = $this->userCountsByProgram('student');
        $advisers = $this->userCountsByProgram('research_adviser');

        $peopleByDept = [];
        foreach ($this->submitterRows() as $person) {
            $code = (string) $person['program_code'];
            $peopleByDept[$code][] = [
                'school_id' => $person['school_id'],
                'name' => $person['last_name'] . ', ' . $person['first_name'],
                'submitted' => (int) $person['submitted'],
                'approved' => (int) $person['approved'],
                'last_update' => (string) $person['last_update'],
            ];
        }

        $departments = [];
        foreach ($programs as $program) {
            $id = (int) $program['id'];
            $row = $counts[$id] ?? [
                'submitted' => 0,
                'approved' => 0,
                'in_review' => 0,
                'revision' => 0,
                'drafts' => 0,
                'archived' => 0,
            ];
            $departments[] = [
                'code' => $program['code'],
                'name' => $program['name'],
                'submitted' => (int) $row['submitted'],
                'approved' => (int) $row['approved'],
                'in_review' => (int) $row['in_review'],
                'revision' => (int) $row['revision'],
                'drafts' => (int) $row['drafts'],
                'archived' => (int) $row['archived'],
                'students' => (int) ($students[$id] ?? 0),
                'advisers' => (int) ($advisers[$id] ?? 0),
                'people' => $peopleByDept[$program['code']] ?? [],
            ];
        }

        view('settings/index', [
            'departments' => $departments,
        ]);
    }

    public function submit(): void
    {
        Csrf::requireValid();
        $uid = (int) Auth::user()['id'];
        Settings::set('max_upload_mb', (string) max(1, (int) ($_POST['max_upload_mb'] ?? 25)), $uid);
        Settings::set('session_timeout_minutes', (string) max(5, (int) ($_POST['session_timeout_minutes'] ?? 30)), $uid);
        Settings::set('plagiarism_block_percent', (string) ($_POST['plagiarism_block_percent'] ?? '10'), $uid);
        Settings::set('ip_allowlist_enabled', isset($_POST['ip_allowlist_enabled']) ? '1' : '0', $uid);
        $cidrs = array_filter(array_map('trim', explode("\n", $_POST['ip_allowlist_cidrs'] ?? '')));
        Settings::set('ip_allowlist_cidrs', json_encode(array_values($cidrs)), $uid);
        Audit::log('settings.update', 'settings', null);
        flash('success', 'Settings saved.');
        redirect('settings');
    }

    /** @return array<int, array<string, int|string>> */
    private function studyCountsByProgram(): array
    {
        $rows = db()->query(
            "SELECT p.id,
                    SUM(s.id IS NOT NULL AND s.status <> 'draft') AS submitted,
                    SUM(s.status = 'approved') AS approved,
                    SUM(s.status IN ('submitted','adviser_reviewed','pending','under_review','ethics_review','resubmitted')) AS in_review,
                    SUM(s.status = 'revision_requested') AS revision,
                    SUM(s.status = 'draft') AS drafts,
                    SUM(s.status = 'archived') AS archived
             FROM programs p
             LEFT JOIN studies s ON s.program_id = p.id
             WHERE p.is_active = 1 AND p.director_added = 1
             GROUP BY p.id"
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    /** @return array<int, list<string>> */
    private function submittersByProgram(): array
    {
        $rows = db()->query(
            "SELECT s.program_id, u.last_name, u.first_name, COUNT(*) AS c
             FROM studies s
             INNER JOIN users u ON u.id = s.submitter_user_id
             INNER JOIN programs p ON p.id = s.program_id
             WHERE p.is_active = 1 AND p.director_added = 1 AND s.status <> 'draft'
             GROUP BY s.program_id, u.id, u.last_name, u.first_name
             ORDER BY u.last_name, u.first_name"
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['program_id'];
            $label = $row['last_name'] . ', ' . $row['first_name'];
            if ((int) $row['c'] > 1) {
                $label .= ' (' . (int) $row['c'] . ')';
            }
            $out[$id][] = $label;
        }
        return $out;
    }

    /** @return array<int, int> */
    private function userCountsByProgram(string $roleSlug): array
    {
        $stmt = db()->prepare(
            'SELECT u.program_id, COUNT(*) AS c
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.slug = :slug AND u.program_id IS NOT NULL
             GROUP BY u.program_id'
        );
        $stmt->execute(['slug' => $roleSlug]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['program_id']] = (int) $row['c'];
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function submitterRows(): array
    {
        return db()->query(
            "SELECT p.code AS program_code, u.school_id, u.last_name, u.first_name,
                    COUNT(*) AS submitted,
                    SUM(s.status = 'approved') AS approved,
                    MAX(s.updated_at) AS last_update
             FROM studies s
             INNER JOIN users u ON u.id = s.submitter_user_id
             INNER JOIN programs p ON p.id = s.program_id
             WHERE p.is_active = 1 AND p.director_added = 1 AND s.status <> 'draft'
             GROUP BY p.code, u.id, u.school_id, u.last_name, u.first_name
             ORDER BY p.code, u.last_name, u.first_name"
        )->fetchAll();
    }
}
