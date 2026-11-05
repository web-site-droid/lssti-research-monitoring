<?php
declare(strict_types=1);
// Isolated authorization tests: no application bootstrap, database, network, or credentials.
namespace App\Helpers {
    final class Auth {
        public static array $actor = ['id'=>1, 'role_slug'=>'account_admin', 'must_change_password'=>0];
        public static function user(): array { return self::$actor; }
        public static function check(): bool { return false; }
        public static function login(array $user): void { $GLOBALS['logged_in']=$user; }
        public static function requireLogin(): void {}
        public static function requireRole($roles): void {
            if (!in_array(self::$actor['role_slug'], (array) $roles, true)) throw new \RuntimeException('forbidden');
        }
    }
    final class Csrf { public static function requireValid(): void {} public static function field(): string {return '<input name="csrf" value="fixture">';} }
    final class Audit { public static function log(...$args): void {} }
}
namespace App\Models {
    final class User {
        public static ?array $loginUser=null;
        public static array $created = [];
        public static array $rows = [
            ['id'=>1, 'role_slug'=>'school_administrator'], ['id'=>2, 'role_slug'=>'student'],
            ['id'=>3, 'role_slug'=>'account_admin'], ['id'=>4, 'role_slug'=>'librarian'],
        ];
        public static function all(): array { return self::$rows; }
        public static function findBySchoolId($id) { return self::$loginUser !== null ? ($id===self::$loginUser['school_id'] ? self::$loginUser : null) : ($id==='TAKEN' ? ['id'=>5] : null); }
        public static function passwordMatches($hash,$password): bool { return password_verify($password,$hash); }
        public static function findRoleBySlug($slug): ?array {
            if ($slug==='school_administrator') return ['id'=>1];
            return $GLOBALS['fixture']->roleCreated ? ['id'=>10] : null;
        }
        public static function create(array $data, int $actor): int {
            self::$created=$data;
            if ($data['role_id']===10) $GLOBALS['fixture']->adminCreated=true;
            return 20;
        }
    }
    final class LoginLog { public static function record(...$args): void {} }
}
namespace {
    final class Fixture {
        public bool $roleCreated=false, $adminCreated=false, $transaction=false;
        public array $queries=[], $params=[];
        public string $sql='';
        public function beginTransaction(): void {$this->transaction=true;}
        public function commit(): void {$this->transaction=false;}
        public function rollBack(): void {$this->transaction=false;}
        public function inTransaction(): bool {return $this->transaction;}
        public function exec($sql): void {$this->roleCreated=true;}
        public function query($sql): self {$this->sql=$sql;return $this;}
        public function fetchColumn(): bool {return $this->adminCreated;}
        public function prepare($sql): self {$this->sql=$sql;$this->queries[]=$sql;return $this;}
        public function execute($params): void {$this->params[]=$params;}
        public function fetchAll(): array {return [];}
    }
    function db(): Fixture {return $GLOBALS['fixture'];}
    function flash(...$args) {return null;}
    function is_post(): bool {return true;}
    function view($name,$data): void {$GLOBALS['view']=[$name,$data];}
    function e($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
    function icon($name): string {return '';}
    function url($route,$params=[]): string {return 'index.php?'.http_build_query(['route'=>$route]+$params);}
    function redirect($route,...$args): never {throw new \RuntimeException('redirect:'.$route);}
    function check(bool $ok,string $why): void {if(!$ok)throw new \RuntimeException($why);$GLOBALS['checks']++;}
    function expect(callable $fn,string $message): void {
        try {$fn();} catch (\RuntimeException $e) {check($e->getMessage()===$message,'Unexpected failure: '.$e->getMessage());return;}
        throw new \RuntimeException('Expected '.$message);
    }
    $GLOBALS['checks']=0;$GLOBALS['fixture']=new Fixture();
    require __DIR__.'/../app/helpers/Role.php';
    require __DIR__.'/../app/models/LibrarianAccounts.php';
    require __DIR__.'/../app/models/Study.php';
    require __DIR__.'/../app/controllers/AccountAdminController.php';
    require __DIR__.'/../app/Router.php';
    use App\Helpers\Auth;
    use App\Helpers\Role;
    use App\Models\LibrarianAccounts;
    use App\Models\User;
    $controller=new \App\Controllers\AccountAdminController();
    check(Role::label('account_admin')==='Admin','Distinct Admin label');
    check(Role::canonical('school_administrator')==='school_administrator','Publisher role identity');
    check(!in_array(Role::ACCOUNT_ADMIN,Role::finalApprovers(),true),'Admin cannot publish');
    check(!in_array(Role::ACCOUNT_ADMIN,Role::peopleManagers(),true),'Admin cannot use general People');
    check(!\App\Models\Study::canOpenFullManuscript(Auth::user(),['status'=>'approved']),'Admin cannot open manuscripts');
    check(array_column(LibrarianAccounts::all(),'id')===[1],'Only final-publisher Librarians listed');
    $_POST=['school_id'=>'LIB-NEW','first_name'=>'New','last_name'=>'Librarian','password'=>'Temporary!123','role_id'=>10,'program_id'=>99];
    expect(fn()=>$controller->create(),'redirect:admin/accounts');
    check(User::$created['role_id']===1 && User::$created['program_id']===null,'Posted privilege/department ignored');
    $_POST['school_id']='TAKEN';expect(fn()=>$controller->create(),'redirect:admin/accounts');
    $_GET=['user_id'=>'2'];$controller->activity();check($GLOBALS['view'][0]==='errors/forbidden','Reject non-Librarian activity');http_response_code(200);
    $_GET=['user_id'=>'invalid'];$controller->activity();check($GLOBALS['view'][0]==='errors/forbidden','Reject malformed ID');http_response_code(200);
    $_GET=['user_id'=>'1'];$controller->activity();
    check($GLOBALS['view'][0]==='account_admin/activity','Valid Librarian activity');
    check(count($GLOBALS['fixture']->queries)===2,'Audit and login sources');
    foreach($GLOBALS['fixture']->queries as $sql)check(str_contains($sql,"r.slug='school_administrator'") && str_contains($sql,'u.id=:uid'),'Server-side activity scope');
    foreach($GLOBALS['fixture']->params as $params)check($params===['uid'=>1],'Bound user filter');
    expect(fn()=>$controller->setupSubmit(),'forbidden');
    Auth::$actor['role_slug']='school_administrator';expect(fn()=>$controller->create(),'forbidden');
    $_POST['username']='ADMIN-NEW';expect(fn()=>$controller->setupSubmit(),'redirect:admin/setup');
    check(User::$created['role_id']===10 && User::$created['program_id']===null,'Setup creates distinct Admin');
    expect(fn()=>LibrarianAccounts::createAdmin($_POST,1),'An Admin account already exists. Setup is closed.');
    check(!$GLOBALS['fixture']->inTransaction(),'Setup failure rolls back');
    Auth::$actor['role_slug']='account_admin';$router=new \App\Router();
    $routeProperty=new \ReflectionProperty($router,'routes');$routeProperty->setAccessible(true);
    $studyRoles=$routeProperty->getValue($router)['study']['roles'];
    check(in_array(Role::SCHOOL_ADMINISTRATOR,$studyRoles,true),'Final-publisher Librarian can open Study');
    check(in_array(Role::SCHOOL_RESEARCH_COORDINATOR,$studyRoles,true),'SRC retains Study access');
    check(!in_array(Role::ACCOUNT_ADMIN,$studyRoles,true),'Account Admin remains excluded from Study');
    foreach(['review','settings','users','file/manuscript','reports','studies','admin/setup'] as $route){
        $router->dispatch($route);check($GLOBALS['view'][0]==='errors/forbidden','Route isolated: '.$route);http_response_code(200);
    }
    $router->dispatch('admin/accounts');check($GLOBALS['view'][0]==='account_admin/accounts','Admin route accessible');
    $accounts=[['id'=>1,'school_id'=>'LIB-1','first_name'=>'<unsafe>','last_name'=>'Name','is_active'=>1]];
    $success=$error=null;
    ob_start();require __DIR__.'/../app/views/account_admin/accounts.php';$html=ob_get_clean();
    check(str_contains($html,'&lt;unsafe&gt;')&&!str_contains($html,'<unsafe>'),'Account names escaped');
    check(str_contains($html,'id="account-admin-create"')&&str_contains($html,'admin%2Faccounts%2Fcreate'),'Creation dialog rendered');
    $exists=true;ob_start();require __DIR__.'/../app/views/account_admin/setup.php';$closed=ob_get_clean();
    check(!str_contains($closed,'<form')&&str_contains($closed,'already configured'),'Closed setup has no creation form');
    require __DIR__.'/../app/controllers/AuthController.php';
    $authController=new \App\Controllers\AuthController();
    User::$loginUser=['id'=>30,'school_id'=>'MYADMIN','role_slug'=>'account_admin','is_active'=>1,'must_change_password'=>0,'password_hash'=>password_hash('Temporary!123',PASSWORD_DEFAULT)];
    $_POST=['username'=>'myadmin','password'=>'Temporary!123'];
    expect(fn()=>$authController->adminLoginSubmit(),'redirect:admin/accounts');
    check(($GLOBALS['logged_in']['id']??null)===30,'Username login accepts Admin');
    unset($GLOBALS['logged_in']);$_POST['password']='wrong';
    expect(fn()=>$authController->adminLoginSubmit(),'redirect:admin/login');
    check(!isset($GLOBALS['logged_in']),'Invalid password does not log in');
    $_POST['password']='Temporary!123';User::$loginUser['role_slug']='school_administrator';
    expect(fn()=>$authController->adminLoginSubmit(),'redirect:admin/login');
    check(!isset($GLOBALS['logged_in']),'Librarian cannot use Admin login');
    User::$loginUser['role_slug']='account_admin';$_POST=['school_id'=>'MYADMIN','password'=>'Temporary!123'];
    expect(fn()=>$authController->loginSubmit(),'redirect:admin/login');
    check(!isset($GLOBALS['logged_in']),'Admin is directed to username login');
    echo 'Passed '.$GLOBALS['checks'].' Admin authorization checks.'.PHP_EOL;
}
