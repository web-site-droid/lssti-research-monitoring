<?php
declare(strict_types=1);
namespace App\Helpers {
    final class Auth {
        public static array $actor=['id'=>1,'role_slug'=>'school_administrator'];
        public static function user(): array {return self::$actor;}
        public static function requireRole($roles): void {if(!in_array(self::$actor['role_slug'],(array)$roles,true))throw new \RuntimeException('forbidden');}
    }
    final class Csrf {public static function requireValid(): void {}}
    final class Audit {public static function log(...$args): void {}}
}
namespace App\Models {final class Program {public static function find($id): array {return ['id'=>$id];}}}
namespace {
    final class Fixture {
        public array $target=['id'=>2,'school_id'=>'OLD-2','role_slug'=>'student','role_name'=>'Student','program_id'=>3,'created_by'=>1];
        public bool $transaction=false,$deleted=false,$duplicate=false;
        public ?string $reference=null;
        public array $updates=[];
        public function beginTransaction(): void {$this->transaction=true;}
        public function commit(): void {$this->transaction=false;}
        public function rollBack(): void {$this->transaction=false;}
        public function inTransaction(): bool {return $this->transaction;}
        public function prepare(string $sql): Statement {return new Statement($this,$sql);}
    }
    final class Statement {
        public array $params=[];
        public function __construct(public Fixture $db,public string $sql){}
        public function execute($params): void {
            $this->params=$params;
            if(str_starts_with($this->sql,'DELETE'))$this->db->deleted=true;
            if(str_starts_with($this->sql,'UPDATE users'))$this->db->updates[]=$params;
        }
        public function fetch() {
            if(str_contains($this->sql,'FROM roles'))return ['id'=>3,'slug'=>'student'];
            if(str_contains($this->sql,'UPPER(TRIM'))return $this->db->duplicate ? ['id'=>99] : false;
            return $this->db->target;
        }
        public function fetchColumn() {
            if(str_contains($this->sql,'FROM roles'))return 1;
            return $this->db->reference!==null && str_contains($this->sql,'FROM '.$this->db->reference.' ');
        }
    }
    function db(): Fixture {return $GLOBALS['db'];}
    function flash(...$args): void {}
    function redirect(...$args): never {throw new \RuntimeException('redirect');}
    function check($ok,$why): void {if(!$ok)throw new \RuntimeException($why);$GLOBALS['checks']++;}
    function blocked(callable $fn,string $message): void {
        try{$fn();}catch(\RuntimeException $e){check(str_contains($e->getMessage(),$message),'Wrong failure: '.$e->getMessage());return;}
        throw new \RuntimeException('Expected rejection');
    }
    require __DIR__.'/../app/helpers/Role.php';
    require __DIR__.'/../app/models/User.php';
    require __DIR__.'/../app/controllers/UserController.php';
    $GLOBALS['checks']=0;$GLOBALS['db']=new Fixture();
    $controller=new \App\Controllers\UserController();
    $_POST=['id'=>2,'school_id'=>' new-2 ','first_name'=>'Test','last_name'=>'Student','role_id'=>3,'program_id'=>3,'is_active'=>1];
    blocked(fn()=>$controller->editSubmit(),'redirect');
    check($GLOBALS['db']->updates[0]['sid']==='NEW-2','ID edit normalized and saved');
    $GLOBALS['db']->updates=[];$GLOBALS['db']->duplicate=true;
    blocked(fn()=>$controller->editSubmit(),'redirect');check(!$GLOBALS['db']->updates,'Duplicate ID rejected');
    $GLOBALS['db']->duplicate=false;$_POST['school_id']='';blocked(fn()=>$controller->editSubmit(),'redirect');check(!$GLOBALS['db']->updates,'Empty ID rejected');
    $_POST['school_id']=str_repeat('X',33);blocked(fn()=>$controller->editSubmit(),'redirect');check(!$GLOBALS['db']->updates,'Oversized ID rejected');
    \App\Helpers\Auth::$actor['role_slug']='school_research_coordinator';$_POST['school_id']='FORGED-ID';
    blocked(fn()=>$controller->editSubmit(),'redirect');check($GLOBALS['db']->updates[0]['sid']==='FORGED-ID','SRC can edit ID');
    $GLOBALS['db']->updates=[];\App\Helpers\Auth::$actor['role_slug']='research_adviser';\App\Helpers\Auth::$actor['program_id']=3;
    blocked(fn()=>$controller->editSubmit(),'redirect');check(!array_key_exists('sid',$GLOBALS['db']->updates[0]),'Adviser cannot forge ID edits');
    \App\Helpers\Auth::$actor['role_slug']='school_administrator';
    $GLOBALS['db']=new Fixture();
    foreach(['studies','study_versions','review_assignments','review_comments','review_scores','plagiarism_results','read_logs'] as $table){
        $GLOBALS['db']->reference=$table;
        blocked(fn()=>\App\Models\User::deleteWithoutAcademicRecords(2,1),'Deactivate');
        check(!$GLOBALS['db']->deleted&&!$GLOBALS['db']->inTransaction(),'Referenced record preserved');
    }
    $GLOBALS['db']->reference=null;
    blocked(fn()=>\App\Models\User::deleteWithoutAcademicRecords(2,2),'signed-in');
    $GLOBALS['db']->target['role_slug']='account_admin';blocked(fn()=>\App\Models\User::deleteWithoutAcademicRecords(2,1),'Admin accounts');
    $GLOBALS['db']->target['role_slug']='student';\App\Models\User::deleteWithoutAcademicRecords(2,1);
    check($GLOBALS['db']->deleted&&!$GLOBALS['db']->inTransaction(),'Unlinked account deleted and transaction committed');
    foreach(['dean','account_admin','student'] as $role){
        \App\Helpers\Auth::$actor['role_slug']=$role;blocked(fn()=>$controller->deleteSubmit(),'forbidden');
    }
    $GLOBALS['db']=new Fixture();\App\Helpers\Auth::$actor['role_slug']='school_research_coordinator';$_POST=['id'=>2];
    blocked(fn()=>$controller->deleteSubmit(),'redirect');check($GLOBALS['db']->deleted,'SRC can delete unlinked user');
    $GLOBALS['db']=new Fixture();$GLOBALS['db']->reference='studies';
    blocked(fn()=>$controller->deleteSubmit(),'redirect');check(!$GLOBALS['db']->deleted,'SRC cannot delete user linked to studies');
    echo 'Passed '.$GLOBALS['checks'].' Librarian management checks.'.PHP_EOL;
}
