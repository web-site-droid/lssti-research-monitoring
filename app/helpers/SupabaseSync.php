<?php

declare(strict_types=1);

namespace App\Helpers;

final class SupabaseSync
{
    public static function isConfigured(): bool
    {
        return SUPABASE_URL !== '' && SUPABASE_SERVICE_ROLE_KEY !== '';
    }

    public static function isEnabled(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        return Settings::get('supabase_enabled') === '1';
    }

    public static function pushAudit(
        ?int $localId,
        ?string $schoolId,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $details,
        string $ip
    ): void {
        if (!self::isEnabled() || Settings::get('supabase_sync_audit') !== '1') {
            return;
        }
        self::post('research_audit_logs', [
            'local_id' => $localId,
            'user_school_id' => $schoolId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $ip,
        ]);
    }

    public static function pushReadLog(
        ?int $localId,
        string $schoolId,
        int $studyId,
        int $versionId,
        string $ip
    ): void {
        if (!self::isEnabled() || Settings::get('supabase_sync_read') !== '1') {
            return;
        }
        self::post('research_read_logs', [
            'local_id' => $localId,
            'user_school_id' => $schoolId,
            'study_id' => $studyId,
            'study_version_id' => $versionId,
            'ip_address' => $ip,
        ]);
    }

    /** @param array<string, mixed> $row */
    public static function upsertStudyIndex(array $row): void
    {
        if (!self::isEnabled() || Settings::get('supabase_sync_studies') !== '1') {
            return;
        }
        self::post('research_study_index', $row, 'on_conflict=local_study_id', true);
    }

    public static function mirrorFileToStorage(string $localRelativePath, string $objectPath): void
    {
        if (!self::isEnabled() || Settings::get('supabase_storage_mirror') !== '1') {
            return;
        }
        $full = STORAGE_PATH . '/' . ltrim($localRelativePath, '/');
        if (!is_file($full)) {
            return;
        }
        $objectPath = str_replace('\\', '/', ltrim($objectPath, '/'));
        $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/' . SUPABASE_STORAGE_BUCKET . '/' . $objectPath;
        $body = file_get_contents($full);
        if ($body === false) {
            return;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($full) ?: 'application/octet-stream';
        self::request('POST', $url, $body, [
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private static function post(string $table, array $payload, string $query = '', bool $upsert = false): void
    {
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/' . rawurlencode($table);
        if ($query !== '') {
            $url .= '?' . $query;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        $prefer = $upsert ? 'return=minimal,resolution=merge-duplicates' : 'return=minimal';
        self::request('POST', $url, $json, [
            'Content-Type: application/json',
            'Prefer: ' . $prefer,
        ]);
    }

    public static function syncStudyFromRow(array $study): void
    {
        self::upsertStudyIndex([
            'local_study_id' => (int) $study['id'],
            'title' => $study['title'],
            'short_title' => $study['short_title'],
            'status' => $study['status'],
            'program_code' => $study['program_code'] ?? '',
            'school_year' => $study['school_year'],
            'research_objectives' => $study['research_objectives'] ?? '',
            'approved_at' => $study['approved_at'] ?? null,
        ]);
    }

    /** @param list<string> $extraHeaders */
    private static function request(string $method, string $url, string $body, array $extraHeaders = []): void
    {
        if (!self::isConfigured()) {
            return;
        }
        $headers = array_merge([
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        ], $extraHeaders);
        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function connectionStatus(): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'message' => 'Add config/supabase.local.php with URL and service role key.'];
        }
        $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/research_study_index?select=local_study_id&limit=1';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
                'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'message' => 'Connected to Supabase (server-side).'];
        }
        return ['ok' => false, 'message' => 'Supabase reachable but returned HTTP ' . $code . '. Check service role key.'];
    }
}
