<?php

declare(strict_types=1);

define('APP_NAME', 'Lanao School of Science and Technology Digital Research Repository');

define('BASE_PATH', dirname(__DIR__));
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', BASE_PATH . '/config');
}
define('PUBLIC_PATH', BASE_PATH . '/public');

if (!function_exists('lssti_env')) {
    function lssti_env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    }
}

if (!defined('VERCEL_DEPLOY')) {
    define('VERCEL_DEPLOY', lssti_env('VERCEL') === '1' || lssti_env('VERCEL') === 'true');
}

define('STORAGE_PATH', VERCEL_DEPLOY
    ? (rtrim(sys_get_temp_dir(), '/\\') . '/lssti-storage')
    : (BASE_PATH . '/storage'));

/**
 * Local XAMPP uses root with no password.
 * Hostinger uses the hPanel MySQL account.
 * Vercel reads DB_* from project environment variables.
 * config/database.local.php overrides when that file exists (not used on Vercel).
 */
$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocalDb = $httpHost === ''
    || str_starts_with($httpHost, 'localhost')
    || str_starts_with($httpHost, '127.0.0.1');

if (!VERCEL_DEPLOY && is_file(CONFIG_PATH . '/database.local.php')) {
    require CONFIG_PATH . '/database.local.php';
}

if (!defined('DB_HOST')) {
    define('DB_HOST', lssti_env('DB_HOST', lssti_env('MYSQL_HOST', VERCEL_DEPLOY ? '' : '127.0.0.1')) ?? '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int) (lssti_env('DB_PORT', lssti_env('MYSQL_PORT', '3306')) ?? '3306'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', lssti_env('DB_NAME', lssti_env('MYSQL_DATABASE', 'u471965942_researchdb')) ?? 'u471965942_researchdb');
}
if (!defined('DB_USER')) {
    $defaultUser = ($isLocalDb && !VERCEL_DEPLOY) ? 'root' : 'u471965942_root';
    define('DB_USER', lssti_env('DB_USER', lssti_env('MYSQL_USER', $defaultUser)) ?? $defaultUser);
}
if (!defined('DB_PASS')) {
    $defaultPass = ($isLocalDb && !VERCEL_DEPLOY) ? '' : 'Lssti2026';
    define('DB_PASS', lssti_env('DB_PASS', lssti_env('MYSQL_PASSWORD', $defaultPass)) ?? $defaultPass);
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

define('APP_ASSET_VERSION', '20261015e');
define('SESSION_NAME', 'LSSTI_RESEARCH_SESS');
define('SESSION_TIMEOUT_MINUTES', 30);
define('CSRF_TOKEN_KEY', '_csrf_token');

define('DEFAULT_MAX_UPLOAD_MB', VERCEL_DEPLOY ? 4 : 25);
define('DEFAULT_PLAGIARISM_BLOCK_PERCENT', 10.0);
define('DEFAULT_LOGIN_MAX_ATTEMPTS', 5);
define('DEFAULT_LOGIN_LOCKOUT_MINUTES', 15);

/** Optional LAN allowlist (empty = disabled). CIDR strings, e.g. 192.168.0.0/16 */
define('IP_ALLOWLIST_ENABLED', false);
define('IP_ALLOWLIST_CIDRS', [
    '192.168.0.0/16',
    '10.0.0.0/8',
]);

/** Fallback when not running under Apache; browser requests use auto-detected path + port */
define('APP_URL_PATH', VERCEL_DEPLOY ? '' : '/research/public');

/** Set if Apache listens on a non-default port (e.g. 8080). Leave 0 to auto-detect from the browser URL. */
define('APP_HTTP_PORT', 0);
