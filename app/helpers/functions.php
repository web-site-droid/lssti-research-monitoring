<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Web path to public folder (no scheme/host), e.g. /lssti_research_repository/public */
function app_url_path(): string
{
    if (defined('VERCEL_DEPLOY') && VERCEL_DEPLOY) {
        return '';
    }
    if (php_sapi_name() !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
        $dir = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
        if ($dir === '/' || $dir === '.') {
            return '';
        }
        return rtrim($dir, '/');
    }
    return rtrim(APP_URL_PATH, '/');
}

/** Full origin for rare absolute links, includes port when needed (e.g. http://localhost:8080) */
function app_origin(): string
{
    if (php_sapi_name() === 'cli') {
        $port = APP_HTTP_PORT > 0 ? APP_HTTP_PORT : 80;
        return 'http://127.0.0.1' . ($port === 80 ? '' : ':' . $port) . app_url_path();
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (APP_HTTP_PORT > 0 && !str_contains($host, ':')) {
        $host .= ':' . APP_HTTP_PORT;
    }
    return $scheme . '://' . $host . app_url_path();
}

function asset_url(string $relativePath): string
{
    $path = 'assets/' . ltrim($relativePath, '/');
    $full = PUBLIC_PATH . '/' . $path;
    $mtime = is_file($full) ? (string) filemtime($full) : '1';
    $stamp = defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1';
    return $path . '?v=' . rawurlencode($stamp . '.' . $mtime);
}

function url(string $route = '', array $query = []): string
{
    $params = array_merge(['route' => $route], $query);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $route, array $query = []): never
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Location: ' . url($route, $query));
    exit;
}

/** Keep typed form values across a validation redirect. Files cannot be restored. */
function remember_form(array $data): void
{
    $_SESSION['_old'] = $data;
}

/** @return array<string, mixed> */
function form_old(): array
{
    static $loaded = false;
    static $data = [];
    if (!$loaded) {
        $data = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        $loaded = true;
    }
    return $data;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $msg = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function view(string $name, array $data = [], string $layout = 'main'): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    extract($data, EXTR_SKIP);
    $contentView = APP_PATH . '/views/' . str_replace('.', '/', $name) . '.php';
    if (!is_file($contentView)) {
        http_response_code(500);
        echo 'View not found.';
        exit;
    }
    $layoutFile = APP_PATH . '/views/layouts/' . $layout . '.php';
    if (!is_file($layoutFile)) {
        http_response_code(500);
        echo 'Layout not found.';
        exit;
    }
    require $layoutFile;
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_PATH . '/views/partials/' . $name . '.php';
}

function request_exceeded_post_max(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && empty($_POST)
        && empty($_FILES)
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

function json_response(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return array{url: string, anonKey: string}|null Safe browser config (anon key only). */
function supabase_browser_config(): ?array
{
    if (!defined('SUPABASE_URL') || SUPABASE_URL === '') {
        return null;
    }
    if (!defined('SUPABASE_ANON_KEY') || SUPABASE_ANON_KEY === '') {
        return null;
    }
    return [
        'url' => SUPABASE_URL,
        'anonKey' => SUPABASE_ANON_KEY,
    ];
}

/** Inline icon for buttons. Stroke uses the button text color. */
function icon(string $name): string
{
    $icons = [
        'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499A10.75 10.75 0 0 1 2.062 11.652a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.073"/><path d="m2 2 20 20"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'save' => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
        'file' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
        'key' => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
        'upload' => '<path d="M12 3v12"/><path d="m17 8-5-5-5 5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>',
        'download' => '<path d="M12 15V3"/><path d="m7 10 5 5 5-5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>',
        'pencil' => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'send' => '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
        'search' => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
        'bell' => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
        'filter' => '<path d="M10 20a1 1 0 0 0 .553.894l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/>',
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'clock' => '<path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="10"/>',
        'play' => '<path d="M6 3v18"/><path d="m20 12-14 8V4Z"/>',
        'pause' => '<rect x="14" y="3" width="5" height="18" rx="1"/><rect x="5" y="3" width="5" height="18" rx="1"/>',
        'stop' => '<rect x="5" y="5" width="14" height="14" rx="1"/>',
        'flag' => '<path d="M4 22V4a1 1 0 0 1 .4-.8A6 6 0 0 1 8 2c3 0 5 2 7.333 2q2 0 3.067-.8A1 1 0 0 1 20 4v10a1 1 0 0 1-.4.8A6 6 0 0 1 16 16c-2 0-4-1-5-1s-3.112.56-4.4.8A1 1 0 0 1 4 15"/>',
        'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
        'chart' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 16h8"/><path d="M7 11h12"/><path d="M7 6h3"/>',
        'settings' => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/>',
        'list' => '<path d="M3 5h.01"/><path d="M3 12h.01"/><path d="M3 19h.01"/><path d="M8 5h13"/><path d="M8 12h13"/><path d="M8 19h13"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'home' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
        'message' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'login' => '<path d="m10 17 5-5-5-5"/><path d="M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        'logout' => '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'more' => '<circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="5" cy="12" r="1" fill="currentColor" stroke="none"/>',
    ];
    $inner = $icons[$name] ?? $icons['check'];
    return '<svg class="btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
}

function author_names(?string $authors): array
{
    $parts = preg_split('/\s*;\s*/', trim((string) $authors)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn ($name) => $name !== ''));
}

/** Show one action directly. Two or more go behind the three-dot menu. */
function action_menu(string $html): string
{
    $count = preg_match_all('/<(?:a|button)\b/i', $html);
    if ($count <= 1) {
        return '<div class="review-actions">' . $html . '</div>';
    }
    return '<div class="menu action-menu" data-menu>'
        . '<button type="button" class="btn btn-secondary btn-icon-only" data-menu-button aria-expanded="false" aria-label="Actions" title="Actions">' . icon('more') . '</button>'
        . '<div class="menu-panel action-panel" hidden><div class="review-actions">' . $html . '</div></div>'
        . '</div>';
}

function author_cell(?string $authors, string $studyTitle = ''): string
{
    $list = author_names($authors);
    if ($list === []) {
        return '—';
    }
    if (count($list) <= 2) {
        return e(implode('; ', $list));
    }
    $more = count($list) - 2;
    return '<button type="button" class="author-more" data-authors-quick data-title="' . e($studyTitle) . '" data-authors="' . e(implode("\n", $list)) . '">'
        . e($list[0] . '; ' . $list[1])
        . ' <span>+' . $more . ' more</span></button>';
}

/** @return array<string, string> */
function study_type_options(): array
{
    return [
        'capstone' => 'Capstone',
        'thesis' => 'Thesis',
        'research_article' => 'Research article',
        'research_paper' => 'Research paper',
    ];
}

function study_status_label(?string $status): string
{
    $status = strtolower(trim((string) $status));
    return $status === '' ? '—' : ucwords(str_replace('_', ' ', $status));
}

function study_status_guidance(string $status): array
{
    return match ($status) {
        'draft' => ['With the student for preparation.', 'Student submits to the Research Adviser.'],
        'submitted', 'resubmitted' => ['With the Research Adviser for review.', 'Adviser approves; the student then forwards it to the Department Research Coordinator.'],
        'adviser_reviewed' => ['Approved by the Research Adviser; waiting for the student to forward it.', 'Student sends it to the Department Research Coordinator.'],
        'pending' => ['With the Department Research Coordinator for review.', 'After approval, it goes to the Dean.'],
        'dean_review' => ['With the Dean for review.', 'After approval, it goes to the School Research Coordinator.'],
        'under_review', 'ethics_review' => ['With the School Research Coordinator for academic and compliance review.', 'Once cleared, it goes to the Librarian for final publication.'],
        'library_review', 'admin_review' => ['With the Librarian for final publication review.', 'After final approval, it is published in the repository.'],
        'revision_requested' => ['With the student for requested revisions.', 'Student uploads a revised version and resubmits to the Research Adviser.'],
        'approved' => ['Published in the repository.', 'Review is complete; the study is available under department access rules.'],
        'archived' => ['Not approved; the study has been archived.', 'No automatic next review step.'],
        default => ['The current review location is unavailable.', 'Open the study details for more information.'],
    };
}

function status_badge(?string $status, ?string $label = null): string
{
    $status = strtolower(trim((string) $status));
    $class = preg_replace('/[^a-z0-9_-]/', '', $status) ?: 'other';
    $text = $label !== null && $label !== ''
        ? $label
        : study_status_label($status);
    [$current, $next] = study_status_guidance($status);
    return '<button type="button" class="badge study-status status-help is-' . e($class) . '"'
        . ' data-status-current="' . e($current) . '" data-status-next="' . e($next) . '"'
        . ' aria-label="' . e($text . '. Current: ' . $current . ' Next: ' . $next) . '">'
        . '<span class="status-help-label">' . e($text) . '</span><span class="status-help-icon" aria-hidden="true">i</span></button>';
}

function paper_find_attrs(array $paper): string
{
    $when = (string) ($paper['approved_at'] ?? $paper['submitted_at'] ?? $paper['created_at'] ?? $paper['updated_at'] ?? '');
    $month = '';
    if ($when !== '') {
        $stamp = strtotime($when);
        if ($stamp) {
            $month = date('Y-m', $stamp);
        }
    }
    return ' data-program="' . e((string) ($paper['program_code'] ?? '')) . '"'
        . ' data-type="' . e((string) ($paper['study_type'] ?? '')) . '"'
        . ' data-year="' . e((string) ($paper['school_year'] ?? '')) . '"'
        . ' data-month="' . e($month) . '"'
        . ' data-when="' . e($when) . '"';
}

function can_open_full_file(?array $user, array $study): bool
{
    return (int) ($study['current_version_id'] ?? 0) > 0
        && \App\Models\Study::canOpenFullManuscript($user, $study);
}

function publication_timestamp_label(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Publication date unavailable';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
        return 'Publication date unavailable';
    }
    return $date->format('F j, Y \a\t g:i A');
}

function study_type_label(?string $type): string
{
    $options = study_type_options();
    if (isset($options[$type])) {
        return $options[$type];
    }
    return match ($type) {
        'dissertation' => 'Dissertation',
        'proposal' => 'Proposal',
        'final_defense' => 'Final defense',
        default => $type === null || $type === '' ? '' : ucwords(str_replace('_', ' ', $type)),
    };
}
