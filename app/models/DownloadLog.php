<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Audit;

final class DownloadLog
{
    public static function record(int $studyId, int $versionId): void
    {
        Audit::log('study.download', 'study', $studyId, ['version_id' => $versionId, 'copy' => 'full']);
    }

    /** Latest time each user downloaded each study. Optionally only files in one department. */
    /** @return list<array<string,mixed>> */
    public static function latestPerUserStudy(?int $studyProgramId = null): array
    {
        $sql = "SELECT a.created_at, u.school_id, u.first_name, u.last_name,
                    r.name AS role_name, p.code AS program_code,
                    sp.code AS study_program_code, s.title, s.id AS study_id
             FROM audit_logs a
             INNER JOIN users u ON u.id = a.user_id
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN programs p ON p.id = u.program_id
             LEFT JOIN studies s ON s.id = a.entity_id
             LEFT JOIN programs sp ON sp.id = s.program_id
             WHERE a.action = 'study.download'
               AND a.id = (
                 SELECT MAX(latest.id) FROM audit_logs latest
                 WHERE latest.user_id = a.user_id
                   AND latest.action = 'study.download'
                   AND latest.entity_id = a.entity_id
               )";
        $params = [];
        if ($studyProgramId !== null && $studyProgramId > 0) {
            $sql .= ' AND s.program_id = :pid';
            $params['pid'] = $studyProgramId;
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 200';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Latest time each user downloaded one study. */
    /** @return list<array<string,mixed>> */
    public static function latestForStudy(int $studyId): array
    {
        if ($studyId <= 0) {
            return [];
        }
        $stmt = db()->prepare(
            "SELECT a.created_at AS opened_at, u.school_id, u.first_name, u.last_name,
                    ro.name AS role_name, p.code AS program_code
             FROM audit_logs a
             INNER JOIN users u ON u.id = a.user_id
             INNER JOIN roles ro ON ro.id = u.role_id
             LEFT JOIN programs p ON p.id = u.program_id
             WHERE a.action = 'study.download'
               AND a.entity_id = :sid
               AND a.id = (
                 SELECT MAX(latest.id) FROM audit_logs latest
                 WHERE latest.user_id = a.user_id
                   AND latest.action = 'study.download'
                   AND latest.entity_id = a.entity_id
               )
             ORDER BY a.created_at DESC"
        );
        $stmt->execute(['sid' => $studyId]);
        return $stmt->fetchAll();
    }
}
