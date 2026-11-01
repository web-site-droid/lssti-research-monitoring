<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\StorageFiles;
use App\Models\DownloadLog;
use App\Models\ReadLog;
use App\Models\Study;

final class FileController
{
    public function manuscript(): void
    {
        $studyId = (int) ($_GET['study_id'] ?? 0);
        $versionId = (int) ($_GET['version_id'] ?? 0);
        $study = Study::find($studyId);
        $user = Auth::user();
        if (!$study) {
            http_response_code(403);
            exit('Forbidden');
        }
        $allowed = Study::canOpenFullManuscript($user, $study);
        if (!$allowed) {
            http_response_code(403);
            $role = $user['role_slug'] ?? '';
            $msg = match ($role) {
                'student' => 'Studies from other departments show the title and abstract only. The full file is not available.',
                'dean', 'dept_research_coordinator' => 'Full manuscript is limited to your department. Other departments are title and abstract only.',
                'research_adviser' => 'Full manuscript is limited to students you advise. You may read the abstract only.',
                default => 'Forbidden',
            };
            exit($msg);
        }
        $stmt = db()->prepare('SELECT * FROM study_versions WHERE id = :id AND study_id = :sid');
        $stmt->execute(['id' => $versionId, 'sid' => $studyId]);
        $version = $stmt->fetch();
        if (!$version) {
            http_response_code(404);
            exit('Not found');
        }
        $relative = (string) ($version['stored_path'] ?? '');
        $mime = $version['mime_type'];
        $filename = $version['original_filename'];
        if ($relative === '' || !StorageFiles::exists($relative)) {
            http_response_code(404);
            exit('File missing');
        }
        $inline = isset($_GET['inline']) && $_GET['inline'] !== '0' && $_GET['inline'] !== '';
        if ($inline && $user) {
            ReadLog::record((int) $user['id'], $studyId, $versionId);
        }
        if (!$inline && $user) {
            DownloadLog::record($studyId, $versionId);
        }
        StorageFiles::stream($relative, (string) $mime, (string) $filename, $inline);
    }

    public function ethicsDoc(): void
    {
        $studyId = (int) ($_GET['study_id'] ?? 0);
        $study = Study::find($studyId);
        $user = Auth::user();
        if (!$study || !Study::canAccess($user, $study)) {
            http_response_code(403);
            exit;
        }
        $ethics = Study::ethics($studyId);
        $rel = $ethics['irb_document_path'] ?? '';
        if ($rel === '') {
            http_response_code(404);
            exit;
        }
        StorageFiles::stream($rel, 'application/octet-stream', basename($rel), false);
    }

    public function plagiarismShot(): void
    {
        $studyId = (int) ($_GET['study_id'] ?? 0);
        $user = Auth::user();
        if (!in_array($user['role_slug'], ['school_administrator', 'research_adviser'], true)) {
            http_response_code(403);
            exit;
        }
        $plag = Study::latestPlagiarism($studyId);
        $rel = $plag['screenshot_path'] ?? '';
        if ($rel === '') {
            http_response_code(404);
            exit;
        }
        StorageFiles::stream($rel, 'image/png', basename($rel), true);
    }
}
