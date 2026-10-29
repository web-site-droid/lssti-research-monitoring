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
            $ready = true;
        } catch (\Throwable $e) {
            if (self::usesDatabase()) {
                throw $e;
            }
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
        $full = STORAGE_PATH . '/' . $relative;
        $dir = dirname($full);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not save uploaded file.');
        }
        if (!move_uploaded_file($tmpPath, $full) && !rename($tmpPath, $full) && !copy($tmpPath, $full)) {
            throw new \RuntimeException('Could not save uploaded file.');
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

    public static function exists(string $relative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return false;
        }
        if (is_file(STORAGE_PATH . '/' . $relative)) {
            return true;
        }
        $stmt = db()->prepare('SELECT 1 FROM stored_files WHERE stored_path = :path LIMIT 1');
        $stmt->execute(['path' => $relative]);
        return (bool) $stmt->fetchColumn();
    }

    public static function mime(string $relative, string $fallback = 'application/octet-stream'): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $disk = STORAGE_PATH . '/' . $relative;
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
        $disk = STORAGE_PATH . '/' . $relative;
        if (is_file($disk)) {
            unlink($disk);
        }
        db()->prepare('DELETE FROM stored_files WHERE stored_path = :path')->execute(['path' => $relative]);
    }

    public static function stream(string $relative, string $mime, string $filename, bool $inline): void
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $disk = STORAGE_PATH . '/' . $relative;
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
