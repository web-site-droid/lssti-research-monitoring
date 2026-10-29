<?php

declare(strict_types=1);

namespace App\Helpers;

final class FileUpload
{
    /** @return array{ok:bool,error?:string,mime?:string,ext?:string} */
    public static function validateManuscript(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Manuscript: a file is required.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Manuscript: the upload failed. Try again.'];
        }
        $maxMb = Settings::getInt('max_upload_mb', DEFAULT_MAX_UPLOAD_MB);
        if (defined('VERCEL_DEPLOY') && VERCEL_DEPLOY) {
            $maxMb = min($maxMb, 4);
        }
        if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Manuscript: the file is larger than ' . $maxMb . ' MB.'];
        }
        $name = $file['name'] ?? '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) {
            return ['ok' => false, 'error' => 'Manuscript: only PDF or DOCX files are allowed.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $allowed = [
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        if (!in_array($mime, $allowed[$ext], true)) {
            return ['ok' => false, 'error' => 'Manuscript: the file type does not match the extension. Choose a PDF or DOCX file.'];
        }
        return ['ok' => true, 'mime' => $mime, 'ext' => $ext];
    }

    /** Blind copy for review must be PDF per institutional flow diagram */
    public static function validateBlindPdf(array $file): array
    {
        $check = self::validateManuscript($file);
        if (!$check['ok']) {
            return $check;
        }
        if (($check['ext'] ?? '') !== 'pdf') {
            return ['ok' => false, 'error' => 'Blind copy must be PDF.'];
        }
        return $check;
    }

    public static function storeManuscript(int $studyId, int $versionNumber, array $file, string $ext, string $prefix = ''): string
    {
        $filename = 'v' . $versionNumber . $prefix . '_' . time() . '.' . $ext;
        $relative = 'manuscripts/' . $studyId . '/' . $filename;
        StorageFiles::putUpload($relative, (string) $file['tmp_name'], (string) ($file['type'] ?? 'application/octet-stream'));
        return $relative;
    }

    /** @return array{ok:bool,error?:string} */
    public static function validateImage(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Image upload failed.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return ['ok' => false, 'error' => 'Screenshot must be PNG, JPEG, or WebP.'];
        }
        return ['ok' => true];
    }

    public static function storePlagiarismShot(int $studyId, array $file): string
    {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $filename = 'plag_' . time() . '.' . strtolower((string) $ext);
        $relative = 'plagiarism/' . $studyId . '/' . $filename;
        StorageFiles::putUpload($relative, (string) $file['tmp_name'], (string) ($file['type'] ?? 'image/png'));
        return $relative;
    }

    public static function storeEthicsDoc(int $studyId, array $file): string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'irb_' . time() . '.' . $ext;
        $relative = 'ethics/' . $studyId . '/' . $filename;
        StorageFiles::putUpload($relative, (string) $file['tmp_name'], (string) ($file['type'] ?? 'application/octet-stream'));
        return $relative;
    }
}
