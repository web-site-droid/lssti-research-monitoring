<?php

declare(strict_types=1);

// Standalone regression checks. No application bootstrap, real DB, or network is used.
define('DEFAULT_LOGIN_MAX_ATTEMPTS', 5);
define('DEFAULT_LOGIN_LOCKOUT_MINUTES', 15);
define('DEFAULT_PLAGIARISM_BLOCK_PERCENT', 10.0);
define('CSRF_TOKEN_KEY', '_csrf_token');
define('SUPABASE_URL', '');
define('SUPABASE_SERVICE_ROLE_KEY', '');
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $parts = explode('\\', substr($class, 4));
    if (count($parts) > 1) $parts[0] = strtolower($parts[0]);
    require dirname(__DIR__) . '/app/' . implode('/', $parts) . '.php';
});
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

final class Redirected extends RuntimeException {}
function redirect(string $route, array $params = []): never { throw new Redirected($route); }
function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) $GLOBALS['messages'][$key] = $value;
    return $GLOBALS['messages'][$key] ?? null;
}
function client_ip(): string { return '127.0.0.1'; }
function is_post(): bool { return true; }

final class FixtureDb extends PDO
{
    public array $study = ['id' => 1, 'status' => 'under_review', 'current_version_id' => 7];
    public array|false $ethics;
    public array|false $plagiarism = ['study_version_id' => 7, 'similarity_percentage' => 9.99];
    public array $actor = ['id' => 20, 'role_slug' => 'dean', 'role_name' => 'Dean', 'program_id' => 3];
    public string $selectedRole = 'student';
    public int $failures = 0;
    public array $created = [];
    public array $settings = [];
    public int $userLookups = 0;
    public function __construct() {
        $this->ethics = array_fill_keys(['human_subjects', 'informed_consent', 'ethical_clearance', 'data_privacy_ra10173', 'coi_disclosed', 'manuscript_format'], 'passed');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new FixtureStatement($this, $query); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $stmt = $this->prepare($query); $stmt->execute(); return $stmt;
    }
    public function lastInsertId(?string $name = null): string|false { return '50'; }
    public function result(string $sql, array $params): array {
        if (str_contains($sql, 'FROM settings')) return $this->settings;
        if (str_contains($sql, 'FROM login_logs')) return [[$this->failures]];
        if (str_contains($sql, 'FROM ethics_checklists')) return $this->ethics ? [$this->ethics] : [];
        if (str_contains($sql, 'FROM plagiarism_results')) {
            if (!isset($params['vid'])) throw new RuntimeException('Plagiarism query must specify the current version');
            return $this->plagiarism && $params['vid'] === $this->plagiarism['study_version_id'] ? [$this->plagiarism] : [];
        }
        if (str_contains($sql, 'FROM studies s')) return [$this->study];
        if (str_contains($sql, 'FROM users u')) {
            $this->userLookups++;
            return isset($params['id']) ? [$this->actor] : [];
        }
        if (str_contains($sql, 'FROM roles')) return [['id' => 1, 'slug' => $this->selectedRole]];
        if (str_contains($sql, 'FROM programs')) return [['id' => 3, 'is_active' => 1]];
        if (str_contains($sql, 'INSERT INTO users')) { $this->created[] = $params; return []; }
        if (str_contains($sql, 'INSERT INTO audit_logs')) return [];
        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }
}
final class FixtureStatement extends PDOStatement
{
    private array $params = [];
    private array $rows = [];
    public function __construct(private FixtureDb $fixture, private string $sql) {}
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool {
        $this->params[ltrim((string) $param, ':')] = $value; return true;
    }
    public function execute(?array $params = null): bool {
        $this->rows = $this->fixture->result($this->sql, $params ?? $this->params); return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { $row = $this->fetch(); return $row ? array_values($row)[$column] : false; }
}
$fixture = new FixtureDb();
function db(): PDO { return $GLOBALS['fixture']; }
$checks = 0;
function check(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    $GLOBALS['checks']++;
}
function resetCache(string $class, string $property, mixed $value): void {
    $reflection = new ReflectionProperty($class, $property);
    $reflection->setAccessible(true);
    $reflection->setValue(null, $value);
}

use App\Helpers\Auth;
use App\Helpers\Role;
use App\Helpers\Settings;
use App\Helpers\RateLimit;
use App\Models\Study;

foreach (['school_administrator', 'school_admin', 'administrator', 'director', 'research_director'] as $alias) {
    check(Role::canonical($alias) === Role::SCHOOL_ADMINISTRATOR, 'Administrator alias ' . $alias);
}
check(Role::canonical('librarian') === Role::LIBRARIAN, 'Librarian remains distinct');
check(Role::label('school_administrator', 'Librarian') === 'School Administrator', 'Stale DB label does not rename administrator');
check(!in_array(Role::LIBRARIAN, Role::finalApprovers(), true), 'Librarian cannot final publish');

$_SESSION = ['user_id' => 20, 'role_slug' => 'librarian', CSRF_TOKEN_KEY => 'test-token'];
$fixture->actor['role_slug'] = 'school_administrator';
check(Auth::hasRole(Role::SCHOOL_ADMINISTRATOR), 'Permissions use database role despite stale session role');
check(!Auth::hasRole(Role::LIBRARIAN), 'Stale session role is not retained');
$router = new App\Router();
$routeProperty = new ReflectionProperty($router, 'routes');
$routeProperty->setAccessible(true);
$routes = $routeProperty->getValue($router);
foreach (['study', 'review', 'review/final-publish', 'settings', 'studies/replace-file'] as $route) {
    check(in_array(Role::SCHOOL_ADMINISTRATOR, $routes[$route]['roles'], true), 'Administrator can access ' . $route);
}

foreach (['under_review', 'admin_review', 'library_review'] as $status) {
    $fixture->study['status'] = $status;
    $approval = $status === 'under_review' ? 'canApprove' : 'canFinalPublish';
    $passed = $fixture->ethics;
    check(Study::$approval(1)['ok'], $status . ': valid checks pass');
    foreach (array_keys($passed) as $field) {
        $fixture->ethics[$field] = 'pending';
        check(!Study::$approval(1)['ok'], $status . ': blocks pending ' . $field);
        $fixture->ethics[$field] = 'failed';
        check(!Study::$approval(1)['ok'], $status . ': blocks failed ' . $field);
        $fixture->ethics = $passed;
    }
    $fixture->ethics = false;
    check(!Study::$approval(1)['ok'], $status . ': missing checklist blocks');
    $fixture->ethics = $passed;
    foreach ([10, 10.01, -1, 101, 'invalid'] as $percentage) {
        $fixture->plagiarism['similarity_percentage'] = $percentage;
        check(!Study::$approval(1)['ok'], $status . ': blocks invalid/high similarity');
    }
    $fixture->plagiarism['similarity_percentage'] = 0;
    check(Study::$approval(1)['ok'], $status . ': zero similarity passes');
    $fixture->plagiarism['study_version_id'] = 6;
    check(!Study::$approval(1)['ok'], $status . ': previous version result cannot clear current version');
    $fixture->plagiarism = false;
    check(!Study::$approval(1)['ok'], $status . ': missing result blocks');
    $fixture->plagiarism = ['study_version_id' => 7, 'similarity_percentage' => 9.99];
    $fixture->study['current_version_id'] = null;
    check(!Study::$approval(1)['ok'], $status . ': missing manuscript blocks');
    $fixture->study['current_version_id'] = 7;
}
$fixture->study['status'] = 'draft';
check(!Study::canApprove(1)['ok'] && !Study::canFinalPublish(1)['ok'], 'Draft cannot skip review');
$fixture->settings = [['setting_key' => 'plagiarism_block_percent', 'setting_value' => '5']];
resetCache(Settings::class, 'cache', null);
$fixture->study['status'] = 'under_review';
check(!Study::canApprove(1)['ok'], 'Configured similarity threshold overrides default');
$fixture->settings = [];
resetCache(Settings::class, 'cache', null);

foreach ([0 => false, 4 => false, 5 => true, 6 => true] as $failed => $locked) {
    $fixture->failures = $failed;
    check(RateLimit::isLoginLocked('127.0.0.1') === $locked, 'Login lockout boundary ' . $failed);
}
$lookups = $fixture->userLookups;
$_POST = ['_csrf' => 'test-token'];
try { (new App\Controllers\AuthController())->loginSubmit(); }
catch (Redirected $redirect) { check($redirect->getMessage() === 'login', 'Locked login redirects'); }
check($fixture->userLookups === $lookups, 'Locked login never looks up credentials');

$fixture->actor['role_slug'] = 'dean';
resetCache(Auth::class, 'cached', false);
$_POST = ['_csrf' => 'test-token', 'school_id' => 'TEST-STUDENT', 'first_name' => 'Test', 'last_name' => 'Student', 'role_id' => 1, 'password' => 'test-password-only', 'is_active' => '1', 'program_id' => 999];
try { (new App\Controllers\UserController())->createSubmit(); }
catch (Redirected $redirect) { check($redirect->getMessage() === 'users', 'Dean creation completes'); }
check(count($fixture->created) === 1, 'Dean successfully creates an account');
check($fixture->created[0]['pid'] === 3, 'Dean creation forces own department despite submitted department');
$fixture->selectedRole = 'school_administrator';
try { (new App\Controllers\UserController())->createSubmit(); }
catch (Redirected) {}
check(count($fixture->created) === 1, 'Dean cannot create an administrator');

echo "Passed $checks regression checks (fixture database; no external services).\n";
