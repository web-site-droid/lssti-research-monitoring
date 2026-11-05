<?php

declare(strict_types=1);

namespace App\Helpers;

final class FileUpload
{
    public static function maxMb(): int
    {
        $maxMb = Settings::getInt('max_upload_mb', defined('DEFAULT_MAX_UPLOAD_MB') ? (int) DEFAULT_MAX_UPLOAD_MB : 25);
        if (defined('VERCEL_DEPLOY') && VERCEL_DEPLOY) {
            return max(1, min($maxMb, 15));
        }
        return max(1, $maxMb);
    }

    public static function maxBytes(): int
    {
        return self::maxMb() * 1024 * 1024;
    }

    public static function sizeError(): string
    {
        return 'Manuscript: the file is larger than ' . self::maxMb() . ' MB.';
    }

    /** @return array{ok:bool,error?:string,mime?:string,ext?:string,name?:string,size?:int} */
    public static function validateManuscript(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Manuscript: a file is required.'];
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['ok' => false, 'error' => self::sizeError()];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Manuscript: the upload failed. Try again.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size > self::maxBytes()) {
            return ['ok' => false, 'error' => self::sizeError()];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return ['ok' => false, 'error' => 'Manuscript: the upload failed. Try again.'];
        }
        $head = self::readHead($tmp);
        return self::validateNameAndHead((string) ($file['name'] ?? ''), $head, $size);
    }

    /** @return array{ok:bool,error?:string,mime?:string,ext?:string,name?:string,size?:int,staged?:bool,token?:string} */
    public static function fromStaged(string $token, int $userId): array
    {
        $token = preg_replace('/[^a-f0-9]/', '', $token) ?? '';
        if (strlen($token) < 16 || $userId < 1) {
            return ['ok' => false, 'error' => 'Manuscript: a file is required.'];
        }
        $path = self::stagedPath($userId, $token);
        if (!StorageFiles::exists($path)) {
            return ['ok' => false, 'error' => 'Manuscript: the upload expired. Choose the file again.'];
        }
        $head = StorageFiles::head($path, 8);
        $name = (string) ($_POST['manuscript_name'] ?? 'manuscript.pdf');
        $size = StorageFiles::byteSize($path);
        $check = self::validateNameAndHead($name, $head, $size);
        if (!$check['ok']) {
            return $check;
        }
        $check['staged'] = true;
        $check['token'] = $token;
        $check['name'] = $name;
        $check['size'] = $size;
        return $check;
    }

    /** @return array{ok:bool,error?:string,token?:string,ready?:bool} */
    public static function receivePart(int $userId): array
    {
        $index = (int) ($_POST['index'] ?? -1);
        $total = (int) ($_POST['total'] ?? 0);
        $size = (int) ($_POST['size'] ?? 0);
        $name = (string) ($_POST['name'] ?? '');
        $token = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['token'] ?? '')) ?? '';
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }
        if ($userId < 1 || $index < 0 || $total < 1 || $index >= $total || $total > 40) {
            return ['ok' => false, 'error' => 'Manuscript: the upload failed. Try again.'];
        }
        if ($size < 1 || $size > self::maxBytes()) {
            return ['ok' => false, 'error' => self::sizeError()];
        }
        $chunk = $_FILES['chunk'] ?? [];
        if (($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Manuscript: the upload failed. Try again.'];
        }
        $partPath = 'upload_parts/' . $userId . '/' . $token . '/' . $index;
        StorageFiles::putUpload($partPath, (string) ($chunk['tmp_name'] ?? ''), 'application/octet-stream');
        if ($index < $total - 1) {
            return ['ok' => true, 'token' => $token, 'ready' => false];
        }
        $bytes = '';
        for ($i = 0; $i < $total; $i++) {
            $piece = StorageFiles::getContents('upload_parts/' . $userId . '/' . $token . '/' . $i);
            if ($piece === null) {
                return ['ok' => false, 'error' => 'Manuscript: a part of the file was lost. Try again.'];
            }
            $bytes .= $piece;
            StorageFiles::delete('upload_parts/' . $userId . '/' . $token . '/' . $i);
        }
        $check = self::validateNameAndHead($name, substr($bytes, 0, 8), strlen($bytes));
        if (!$check['ok']) {
            return $check;
        }
        StorageFiles::putContents(self::stagedPath($userId, $token), $bytes, (string) $check['mime']);
        return ['ok' => true, 'token' => $token, 'ready' => true];
    }

    /**
     * @param array{ext:string,mime?:string,staged?:bool,token?:string} $incoming
     */
    public static function storeIncoming(int $studyId, int $versionNumber, array $incoming, array $file, int $userId, string $prefix = ''): string
    {
        $ext = (string) ($incoming['ext'] ?? 'pdf');
        $filename = 'v' . $versionNumber . $prefix . '_' . time() . '.' . $ext;
        $relative = 'manuscripts/' . $studyId . '/' . $filename;
        self::writeIncomingTo($relative, $incoming, $file, $userId);
        return $relative;
    }

    /**
     * @param array{mime?:string,staged?:bool,token?:string} $incoming
     */
    public static function writeIncomingTo(string $relative, array $incoming, array $file, int $userId): void
    {
        $mime = (string) ($incoming['mime'] ?? 'application/octet-stream');
        if (!empty($incoming['staged']) && !empty($incoming['token'])) {
            $staged = self::stagedPath($userId, (string) $incoming['token']);
            $content = StorageFiles::getContents($staged);
            if ($content === null) {
                throw new \RuntimeException('Could not read uploaded file.');
            }
            StorageFiles::putContents($relative, $content, $mime);
            StorageFiles::delete($staged);
            return;
        }
        StorageFiles::putUpload($relative, (string) ($file['tmp_name'] ?? ''), $mime);
    }

    public static function storeManuscript(int $studyId, int $versionNumber, array $file, string $ext, string $prefix = '', string $mime = 'application/octet-stream'): string
    {
        return self::storeIncoming($studyId, $versionNumber, ['ext' => $ext, 'mime' => $mime], $file, 0, $prefix);
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

    /** @return array{ok:bool,error?:string,mime?:string,ext?:string,name?:string,size?:int} */
    private static function validateNameAndHead(string $name, string $head, int $size): array
    {
        if ($size > self::maxBytes()) {
            return ['ok' => false, 'error' => self::sizeError()];
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) {
            return ['ok' => false, 'error' => 'Manuscript: only PDF or DOCX files are allowed.'];
        }
        $mime = self::mimeFromHead($ext, $head);
        if ($mime === null) {
            return ['ok' => false, 'error' => 'Manuscript: the file type does not match the extension. Choose a PDF or DOCX file.'];
        }
        return ['ok' => true, 'mime' => $mime, 'ext' => $ext, 'name' => $name, 'size' => $size];
    }

    private static function mimeFromHead(string $ext, string $head): ?string
    {
        if ($ext === 'pdf' && str_starts_with($head, '%PDF')) {
            return 'application/pdf';
        }
        if ($ext === 'docx' && str_starts_with($head, 'PK')) {
            return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }
        return null;
    }

    private static function readHead(string $path): string
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }
        $head = (string) fread($fh, 8);
        fclose($fh);
        return $head;
    }

    private static function stagedPath(int $userId, string $token): string
    {
        return 'upload_parts/' . $userId . '/' . $token . '/complete';
    }
}
