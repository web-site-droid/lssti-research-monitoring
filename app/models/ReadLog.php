<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\SupabaseSync;

final class ReadLog
{
    public static function record(int $userId, int $studyId, int $versionId): void
    {
        $stmt = db()->prepare(
            'INSERT INTO read_logs (user_id, study_id, study_version_id, ip_address, opened_at)
             VALUES (:uid, :sid, :vid, :ip, NOW())'
        );
        $stmt->execute([
            'uid' => $userId,
            'sid' => $studyId,
            'vid' => $versionId,
            'ip' => client_ip(),
        ]);
        $user = db()->prepare('SELECT school_id FROM users WHERE id = :id');
        $user->execute(['id' => $userId]);
        $schoolId = (string) ($user->fetchColumn() ?: '');
        SupabaseSync::pushReadLog(
            (int) db()->lastInsertId(),
            $schoolId,
            $studyId,
            $versionId,
            client_ip()
        );
    }

    /** @return array<int, array> */
    public static function forStudy(int $studyId, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT r.*, u.school_id, u.first_name, u.last_name, ro.name AS role_name
                FROM read_logs r
                INNER JOIN users u ON u.id = r.user_id
                INNER JOIN roles ro ON ro.id = u.role_id
                WHERE r.study_id = :sid';
        $params = ['sid' => $studyId];
        if ($from) {
            $sql .= ' AND r.opened_at >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $sql .= ' AND r.opened_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }
        $sql .= ' ORDER BY r.opened_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Latest time each user opened each study. Optionally only articles in one department. */
    /** @return list<array<string,mixed>> */
    public static function latestPerUserStudy(?int $studyProgramId = null): array
    {
        $sql = "SELECT r.opened_at, u.school_id, u.first_name, u.last_name,
                    ro.name AS role_name, p.code AS program_code,
                    sp.code AS study_program_code, s.title, s.id AS study_id
             FROM read_logs r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN roles ro ON ro.id = u.role_id
             LEFT JOIN programs p ON p.id = u.program_id
             INNER JOIN studies s ON s.id = r.study_id
             INNER JOIN programs sp ON sp.id = s.program_id
             WHERE r.id = (
                 SELECT MAX(latest.id) FROM read_logs latest
                 WHERE latest.user_id = r.user_id
                   AND latest.study_id = r.study_id
             )";
        $params = [];
        if ($studyProgramId !== null && $studyProgramId > 0) {
            $sql .= ' AND s.program_id = :pid';
            $params['pid'] = $studyProgramId;
        }
        $sql .= ' ORDER BY r.opened_at DESC LIMIT 200';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Latest time each user opened one study. */
    /** @return list<array<string,mixed>> */
    public static function latestForStudy(int $studyId): array
    {
        if ($studyId <= 0) {
            return [];
        }
        $stmt = db()->prepare(
            "SELECT r.opened_at, u.school_id, u.first_name, u.last_name,
                    ro.name AS role_name, p.code AS program_code
             FROM read_logs r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN roles ro ON ro.id = u.role_id
             LEFT JOIN programs p ON p.id = u.program_id
             WHERE r.study_id = :sid
               AND r.id = (
                 SELECT MAX(latest.id) FROM read_logs latest
                 WHERE latest.user_id = r.user_id
                   AND latest.study_id = r.study_id
               )
             ORDER BY r.opened_at DESC"
        );
        $stmt->execute(['sid' => $studyId]);
        return $stmt->fetchAll();
    }
}
