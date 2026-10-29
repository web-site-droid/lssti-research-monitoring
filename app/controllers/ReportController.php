<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Role;
use App\Models\ReadLog;
use App\Models\Study;

final class ReportController
{
    public function index(): void
    {
        $user = Auth::user();
        $isAdmin = Role::isAdmin((string) ($user['role_slug'] ?? ''));
        view('reports/index', [
            'signIns' => $this->signInsByDepartment(null),
            'downloads' => $isAdmin ? $this->fileDownloads() : [],
            'showDownloads' => $isAdmin,
        ]);
    }

    public function auditTrail(): void
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $sql = 'SELECT a.*, u.school_id, u.first_name, u.last_name FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id WHERE 1=1';
        $params = [];
        if ($from) {
            $sql .= ' AND a.created_at >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND a.created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
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
        $studyId = (int) ($_GET['study_id'] ?? 0);
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        view('reports/read_log', [
            'study' => Study::find($studyId),
            'logs' => $studyId ? ReadLog::forStudy($studyId, $from, $to) : [],
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
            $rows = db()->query(
                'SELECT s.id, s.title, COUNT(r.id) AS c FROM read_logs r
                 INNER JOIN studies s ON s.id = r.study_id GROUP BY s.id ORDER BY c DESC LIMIT 50'
            )->fetchAll();
            foreach ($rows as $row) {
                fputcsv($out, [$row['id'], $row['title'], $row['c']]);
            }
        } elseif ($type === 'search_terms') {
            fputcsv($out, ['Query', 'Count']);
            $rows = db()->query(
                'SELECT query_text, COUNT(*) AS c FROM search_logs GROUP BY query_text ORDER BY c DESC LIMIT 100'
            )->fetchAll();
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
            'mostRead' => db()->query(
                'SELECT s.id, s.title, COUNT(r.id) AS c FROM read_logs r
                 INNER JOIN studies s ON s.id = r.study_id GROUP BY s.id ORDER BY c DESC LIMIT 20'
            )->fetchAll(),
            'pending' => db()->query(
                "SELECT s.*, p.code FROM studies s INNER JOIN programs p ON p.id = s.program_id
                 WHERE s.status IN ('ethics_review','under_review','revision_requested','resubmitted')"
            )->fetchAll(),
            'plagiarismHigh' => db()->query(
                'SELECT s.id, s.title, pr.similarity_percentage FROM plagiarism_results pr
                 INNER JOIN studies s ON s.id = pr.study_id WHERE pr.similarity_percentage > 10
                 ORDER BY pr.similarity_percentage DESC'
            )->fetchAll(),
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

    /** @return list<array<string,mixed>> */
    private function fileDownloads(): array
    {
        $stmt = db()->query(
            "SELECT a.created_at, u.school_id, u.first_name, u.last_name,
                    r.name AS role_name, p.code AS program_code, s.title
             FROM audit_logs a
             INNER JOIN users u ON u.id = a.user_id
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN programs p ON p.id = u.program_id
             LEFT JOIN studies s ON s.id = a.entity_id
             WHERE a.action = 'study.download'
               AND a.id = (
                 SELECT MAX(latest.id) FROM audit_logs latest
                 WHERE latest.user_id = a.user_id
                   AND latest.action = 'study.download'
                   AND latest.entity_id = a.entity_id
               )
             ORDER BY a.created_at DESC
             LIMIT 200"
        );
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
        return db()->query(
            'SELECT p.code, s.school_year, s.status, COUNT(*) AS c
             FROM studies s INNER JOIN programs p ON p.id = s.program_id
             GROUP BY p.code, s.school_year, s.status ORDER BY p.code, s.school_year'
        )->fetchAll();
    }
}
