<?php

declare(strict_types=1);

namespace App\Helpers;

final class StorageFiles
{
    public static function ensureSchema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $flag = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lssti-schema-ok';
        if (is_file($flag)) {
            $ready = true;
            return;
        }
        try {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS stored_files (
                    stored_path VARCHAR(255) NOT NULL,
                    mime_type VARCHAR(128) NOT NULL,
                    file_size INT UNSIGNED NOT NULL DEFAULT 0,
                    content LONGBLOB NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (stored_path)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            db()->exec(
                'CREATE TABLE IF NOT EXISTS php_sessions (
                    id VARCHAR(128) NOT NULL,
                    data MEDIUMBLOB NOT NULL,
                    expires_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_php_sessions_expires (expires_at)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            @file_put_contents($flag, '1');
            $ready = true;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public static function repairMissingManuscripts(): void
    {
        if (self::usesDatabase()) {
            return;
        }
        try {
            $rows = db()->query(
                'SELECT id, study_id, stored_path FROM study_versions'
            )->fetchAll();
        } catch (\Throwable $e) {
            return;
        }
        foreach ($rows as $row) {
            $relative = (string) ($row['stored_path'] ?? '');
            $found = self::locate($relative);
            if ($found === null || $found === $relative) {
                continue;
            }
            $disk = self::diskPath($found);
            $ext = strtolower(pathinfo($found, PATHINFO_EXTENSION));
            $mime = $ext === 'pdf'
                ? 'application/pdf'
                : ($ext === 'docx'
                    ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    : 'application/octet-stream');
            $stmt = db()->prepare(
                'UPDATE study_versions
                 SET stored_path = :path, mime_type = :mime, file_size = :size
                 WHERE id = :id'
            );
            $stmt->execute([
                'path' => $found,
                'mime' => $mime,
                'size' => is_file($disk) ? (int) filesize($disk) : 0,
                'id' => (int) $row['id'],
            ]);
        }
    }

    public static function usesDatabase(): bool
    {
        return defined('VERCEL_DEPLOY') && VERCEL_DEPLOY;
    }

    public static function putUpload(string $relative, string $tmpPath, string $mime = 'application/octet-stream'): void
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if (self::usesDatabase()) {
            $content = file_get_contents($tmpPath);
            if ($content === false) {
                throw new \RuntimeException('Could not read uploaded file.');
            }
            self::putContents($relative, $content, $mime);
            return;
        }
        $full = self::diskPath($relative);
        $dir = dirname($full);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not save uploaded file.');
        }
        if (!move_uploaded_file($tmpPath, $full) && !rename($tmpPath, $full) && !copy($tmpPath, $full)) {
            throw new \RuntimeException('Could not save uploaded file.');
        }
        try {
            $saved = file_get_contents($full);
            if ($saved !== false) {
                self::putContents($relative, $saved, $mime);
            }
        } catch (\Throwable $e) {
            // Disk copy is enough on a local server if the blob table is unavailable.
        }
    }

    public static function putContents(string $relative, string $content, string $mime = 'application/octet-stream'): void
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $stmt = db()->prepare(
            'REPLACE INTO stored_files (stored_path, mime_type, file_size, content, created_at)
             VALUES (:path, :mime, :size, :content, NOW())'
        );
        $stmt->execute([
            'path' => $relative,
            'mime' => $mime,
            'size' => strlen($content),
            'content' => $content,
        ]);
    }

    public static function getContents(string $relative): ?string
    {
        $relative = self::locate($relative) ?? ltrim(str_replace('\\', '/', $relative), '/');
        $disk = self::diskPath($relative);
        if (is_file($disk)) {
            $bytes = file_get_contents($disk);
            return $bytes === false ? null : $bytes;
        }
        $stmt = db()->prepare('SELECT content FROM stored_files WHERE stored_path = :path LIMIT 1');
        $stmt->execute(['path' => $relative]);
        $bytes = $stmt->fetchColumn();
        return is_string($bytes) ? $bytes : null;
    }

    public static function head(string $relative, int $length = 8): string
    {
        $length = max(1, $length);
        $relative = self::locate($relative) ?? ltrim(str_replace('\\', '/', $relative), '/');
        $disk = self::diskPath($relative);
        if (is_file($disk)) {
            $fh = fopen($disk, 'rb');
            if ($fh === false) {
                return '';
            }
            $head = (string) fread($fh, $length);
            fclose($fh);
            return $head;
        }
        $stmt = db()->prepare('SELECT LEFT(content, ' . $length . ') FROM stored_files WHERE stored_path = :path LIMIT 1');
        $stmt->execute(['path' => $relative]);
        $head = $stmt->fetchColumn();
        return is_string($head) ? $head : '';
    }

    public static function byteSize(string $relative): int
    {
        $relative = self::locate($relative) ?? ltrim(str_replace('\\', '/', $relative), '/');
        $disk = self::diskPath($relative);
        if (is_file($disk)) {
            return (int) filesize($disk);
        }
        $stmt = db()->prepare('SELECT file_size FROM stored_files WHERE stored_path = :path LIMIT 1');
        $stmt->execute(['path' => $relative]);
        return (int) $stmt->fetchColumn();
    }

    public static function diskPath(string $relative): string
    {
        $relative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
        return rtrim((string) STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR . $relative;
    }

    public static function locate(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return null;
        }
        if (is_file(self::diskPath($relative))) {
            return $relative;
        }
        try {
            $stmt = db()->prepare('SELECT 1 FROM stored_files WHERE stored_path = :path LIMIT 1');
            $stmt->execute(['path' => $relative]);
            if ($stmt->fetchColumn()) {
                return $relative;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet on a fresh local install.
        }
        if (!preg_match('#^manuscripts/(\d+)/([^/]+)$#', $relative, $match)) {
            return null;
        }
        $studyId = $match[1];
        $wanted = $match[2];
        $dir = rtrim((string) STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'manuscripts' . DIRECTORY_SEPARATOR . $studyId;
        if (!is_dir($dir)) {
            return null;
        }
        $prefix = '';
        if (preg_match('/^(v\d+)/i', $wanted, $ver)) {
            $prefix = strtolower($ver[1]);
        }
        $best = null;
        $bestTime = -1;
        $prefixed = null;
        $prefixedTime = -1;
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($full)) {
                continue;
            }
            $mtime = (int) filemtime($full);
            if ($mtime >= $bestTime) {
                $best = $name;
                $bestTime = $mtime;
            }
            if ($prefix !== '' && str_starts_with(strtolower($name), $prefix)) {
                if ($mtime >= $prefixedTime) {
                    $prefixed = $name;
                    $prefixedTime = $mtime;
                }
            }
        }
        $pick = $prefixed ?: $best;
        return $pick ? 'manuscripts/' . $studyId . '/' . $pick : null;
    }

    public static function exists(string $relative): bool
    {
        return self::locate($relative) !== null;
    }

    public static function mime(string $relative, string $fallback = 'application/octet-stream'): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $found = self::locate($relative) ?? $relative;
        $disk = self::diskPath($found);
        if (is_file($disk)) {
            return mime_content_type($disk) ?: $fallback;
        }
        $stmt = db()->prepare('SELECT mime_type FROM stored_files WHERE stored_path = :path LIMIT 1');
        $stmt->execute(['path' => $relative]);
        $mime = $stmt->fetchColumn();
        return is_string($mime) && $mime !== '' ? $mime : $fallback;
    }

    public static function delete(string $relative): void
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return;
        }
        $disk = self::diskPath($relative);
        if (is_file($disk)) {
            unlink($disk);
        }
        db()->prepare('DELETE FROM stored_files WHERE stored_path = :path')->execute(['path' => $relative]);
    }

    public static function stream(string $relative, string $mime, string $filename, bool $inline): void
    {
        $relative = self::locate($relative) ?? ltrim(str_replace('\\', '/', $relative), '/');
        $disk = self::diskPath($relative);
        if (is_file($disk)) {
            header('Content-Type: ' . $mime);
            $disp = $inline ? 'inline' : 'attachment';
            header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($filename) . '"');
            header('Content-Length: ' . (string) filesize($disk));
            readfile($disk);
            exit;
        }

        $stmt = db()->prepare('SELECT mime_type, file_size, content FROM stored_files WHERE stored_path = :path LIMIT 1');
        $stmt->execute(['path' => $relative]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            exit('File missing');
        }
        $type = (string) ($row['mime_type'] ?: $mime);
        header('Content-Type: ' . $type);
        $disp = $inline ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($filename) . '"');
        $bytes = (string) $row['content'];
        header('Content-Length: ' . (string) strlen($bytes));
        echo $bytes;
        exit;
    }
}
