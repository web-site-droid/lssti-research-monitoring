<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Role;
use App\Models\DownloadLog;
use App\Models\Program;
use App\Models\ReadLog;
use App\Models\Study;

final class ReportController
{
    public function index(): void
    {
        $user = Auth::user();
        $role = Role::canonical((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
        $ownDept = in_array($role, [Role::DEAN, Role::DEPT_RESEARCH_COORDINATOR], true);
        $viewProgramCode = $ownDept ? trim((string) ($user['program_code'] ?? '')) : '';
        $studyProgramId = $ownDept ? (int) ($user['program_id'] ?? 0) : 0;
        view('reports/index', [
            'signIns' => $ownDept && $studyProgramId <= 0 ? [] : $this->signInsByDepartment($ownDept ? $studyProgramId : null),
            'reads' => $ownDept && $studyProgramId <= 0 ? [] : ReadLog::latestPerUserStudy($ownDept ? $studyProgramId : null),
            'downloads' => $ownDept && $studyProgramId <= 0 ? [] : DownloadLog::latestPerUserStudy($ownDept ? $studyProgramId : null),
            'programs' => array_values(array_filter(Program::active(), static fn (array $program): bool => !$ownDept || (int) $program['id'] === $studyProgramId)),
            'viewProgramCode' => $viewProgramCode,
            'readsOwnDepartment' => $ownDept,
        ]);
    }

    public function auditTrail(): void
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $sql = 'SELECT a.*, u.school_id, u.first_name, u.last_name FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id WHERE 1=1';
        $params = [];
        $departmentId = $this->departmentScope();
        if ($departmentId !== null) {
            $sql .= ' AND u.program_id = :scope_pid';
            $params['scope_pid'] = $departmentId;
            if ($departmentId <= 0) {
                $sql .= ' AND 1=0';
            }
        }
        if ($from) {
            $sql .= ' AND a.created_at >= :from';
            $params['from'] = (new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone('Asia/Manila')))
                ->setTimezone(new \DateTimeZone(DB_TIMEZONE))->format('Y-m-d H:i:s');
        }
        if ($to) {
            $sql .= ' AND a.created_at <= :to';
            $params['to'] = (new \DateTimeImmutable($to . ' 23:59:59', new \DateTimeZone('Asia/Manila')))
                ->setTimezone(new \DateTimeZone(DB_TIMEZONE))->format('Y-m-d H:i:s');
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 500';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        view('reports/audit_trail', [
            'logs' => $stmt->fetchAll(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function readLog(): void
    {
        $user = Auth::user();
        $studyId = (int) ($_GET['study_id'] ?? 0);
        $study = $studyId ? Study::find($studyId) : null;
        $departmentId = $this->departmentScope();
        if ($studyId && (!$study || !Role::canSeeArticleReaders($user, $study)
            || ($departmentId !== null && ($departmentId <= 0 || (int) $study['program_id'] !== $departmentId)))) {
            http_response_code(403);
            view('errors/forbidden', ['message' => 'You can only see who read articles in your department.']);
            return;
        }
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        view('reports/read_log', [
            'study' => $study,
            'logs' => $studyId ? ReadLog::forStudy($studyId, $from, $to) : [],
            'readers' => $studyId ? ReadLog::latestForStudy($studyId) : [],
            'downloaders' => $studyId ? DownloadLog::latestForStudy($studyId) : [],
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function exportCsv(): void
    {
        $type = $_GET['type'] ?? 'studies';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lssti_report_' . $type . '.csv"');
        $out = fopen('php://output', 'w');
        if ($type === 'studies') {
            fputcsv($out, ['Program', 'School Year', 'Status', 'Count']);
            foreach ($this->studiesGrouped() as $row) {
                fputcsv($out, [$row['code'], $row['school_year'], $row['status'], $row['c']]);
            }
        } elseif ($type === 'most_read') {
            fputcsv($out, ['Study ID', 'Title', 'Read Count']);
            $rows = $this->scopedRows(
                'SELECT s.id, s.title, COUNT(r.id) AS c FROM read_logs r
                 INNER JOIN studies s ON s.id = r.study_id WHERE {{scope}} GROUP BY s.id ORDER BY c DESC LIMIT 50', 's.program_id'
            );
            foreach ($rows as $row) {
                fputcsv($out, [$row['id'], $row['title'], $row['c']]);
            }
        } elseif ($type === 'search_terms') {
            fputcsv($out, ['Query', 'Count']);
            $rows = $this->scopedRows(
                'SELECT l.query_text, COUNT(*) AS c FROM search_logs l LEFT JOIN users u ON u.id = l.user_id WHERE {{scope}} GROUP BY l.query_text ORDER BY c DESC LIMIT 100', 'u.program_id'
            );
            foreach ($rows as $row) {
                fputcsv($out, [$row['query_text'], $row['c']]);
            }
        }
        fclose($out);
        exit;
    }

    public function printView(): void
    {
        $report = $_GET['report'] ?? 'summary';
        $data = [
            'report' => $report,
            'studiesGrouped' => $this->studiesGrouped(),
            'mostRead' => $this->scopedRows(
                'SELECT s.id, s.title, COUNT(r.id) AS c FROM read_logs r
                 INNER JOIN studies s ON s.id = r.study_id WHERE {{scope}} GROUP BY s.id ORDER BY c DESC LIMIT 20', 's.program_id'
            ),
            'pending' => $this->scopedRows(
                "SELECT s.*, p.code FROM studies s INNER JOIN programs p ON p.id = s.program_id
                 WHERE {{scope}} AND s.status IN ('ethics_review','under_review','revision_requested','resubmitted')", 's.program_id'
            ),
            'plagiarismHigh' => $this->scopedRows(
                'SELECT s.id, s.title, pr.similarity_percentage FROM plagiarism_results pr
                 INNER JOIN studies s ON s.id = pr.study_id WHERE {{scope}} AND pr.similarity_percentage > 10
                 ORDER BY pr.similarity_percentage DESC', 's.program_id'
            ),
        ];
        extract($data, EXTR_SKIP);
        $pageTitle = 'Report · ' . APP_NAME;
        require APP_PATH . '/views/layouts/print.php';
        exit;
    }

    /** @return list<array<string,mixed>> */
    private function signInsByDepartment(?int $programId): array
    {
        $sql = "SELECT u.school_id, u.first_name, u.last_name, r.name AS role_name,
                    p.code AS program_code, p.name AS program_name, MAX(l.created_at) AS last_login
             FROM login_logs l
             INNER JOIN users u ON u.id = l.user_id
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN programs p ON p.id = u.program_id
             WHERE l.event = 'login_success'";
        $params = [];
        if ($programId) {
            $sql .= ' AND u.program_id = :pid';
            $params['pid'] = $programId;
        }
        $sql .= ' GROUP BY u.id, u.school_id, u.first_name, u.last_name, r.name, p.code, p.name
             ORDER BY p.code IS NULL, p.code, u.last_name, u.first_name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<array{status:string,c:int}> */
    private function studiesByStatus(): array
    {
        return db()->query('SELECT status, COUNT(*) AS c FROM studies GROUP BY status')->fetchAll();
    }

    /** @return list<array> */
    private function studiesGrouped(): array
    {
        return $this->scopedRows(
            'SELECT p.code, s.school_year, s.status, COUNT(*) AS c
             FROM studies s INNER JOIN programs p ON p.id = s.program_id
             WHERE {{scope}} GROUP BY p.code, s.school_year, s.status ORDER BY p.code, s.school_year', 's.program_id'
        );
    }

    /** Null means school-wide access; zero means no assigned department. */
    private function departmentScope(): ?int
    {
        $user = Auth::user();
        $role = Role::canonical((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
        return in_array($role, [Role::DEAN, Role::DEPT_RESEARCH_COORDINATOR], true)
            ? max(0, (int) ($user['program_id'] ?? 0)) : null;
    }

    private function scopedRows(string $sql, string $column): array
    {
        $departmentId = $this->departmentScope();
        if ($departmentId === 0) {
            return [];
        }
        $sql = str_replace('{{scope}}', $departmentId === null ? '1=1' : $column . ' = :scope_pid', $sql);
        $stmt = db()->prepare($sql);
        $stmt->execute($departmentId === null ? [] : ['scope_pid' => $departmentId]);
        return $stmt->fetchAll();
    }
}
