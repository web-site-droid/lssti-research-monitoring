<?php
declare(strict_types=1);
namespace App\Helpers {
    final class Auth {
        public static array $actor=['id'=>1,'role_slug'=>'school_administrator'];
        public static function user(): array {return self::$actor;}
        public static function requireRole($roles): void {if(!in_array(self::$actor['role_slug'],(array)$roles,true))throw new \RuntimeException('forbidden');}
    }
    final class Csrf {public static function requireValid(): void {}}
    final class Audit {public static bool $fail=false;public static function log(...$args): void {if(self::$fail)throw new \RuntimeException('audit failure');$GLOBALS['audit']=$args;}}
    final class StorageFiles {public static function diskPath($path): string {return __DIR__.'/nonexistent-deletion-fixture/'.$path;}}
}
namespace {
    final class Fixture {
        public string $status='approved';
        public bool $transaction=false,$deleted=false,$race=false;
        public array $removed=[];
        public function beginTransaction(): void {$this->transaction=true;}
        public function commit(): void {$this->transaction=false;}
        public function rollBack(): void {$this->transaction=false;$this->deleted=false;$this->removed=[];}
        public function inTransaction(): bool {return $this->transaction;}
        public function prepare($sql): Statement {return new Statement($this,$sql);}
    }
    final class Statement {
        public function __construct(public Fixture $db,public string $sql){}
        public function execute($params): void {
            if(str_starts_with($this->sql,'DELETE FROM studies'))$this->db->deleted=!$this->db->race;
            if(str_starts_with($this->sql,'DELETE FROM stored_files'))$this->db->removed[]=$params['path'];
        }
        public function fetch(): array {return ['id'=>5,'title'=>'Published title','status'=>$this->db->status];}
        public function rowCount(): int {return $this->db->race?0:1;}
        public function fetchAll(): array {
            if(str_contains($this->sql,'blind_stored_path'))return [['path'=>'manuscripts/5/blind.pdf']];
            if(str_contains($this->sql,'irb_document_path'))return [['path'=>'ethics/5/clearance.pdf']];
            if(str_contains($this->sql,'screenshot_path'))return [['path'=>'plagiarism/5/result.png']];
            return [['path'=>'manuscripts/5/v1.pdf'],['path'=>'manuscripts/6/other.pdf'],['path'=>'manuscripts/5/../other.pdf']];
        }
    }
    function db(): Fixture {return $GLOBALS['db'];}
    function flash(...$args): void {}
    function redirect(...$args): never {throw new \RuntimeException('redirect');}
    function check($ok,$message): void {if(!$ok)throw new \RuntimeException($message);$GLOBALS['checks']++;}
    function rejects(callable $fn,string $message): void {
        try{$fn();}catch(\RuntimeException $error){check(str_contains($error->getMessage(),$message),'Unexpected failure '.$error->getMessage());return;}
        throw new \RuntimeException('Expected rejection');
    }
    require __DIR__.'/../app/helpers/Role.php';
    require __DIR__.'/../app/models/Study.php';
    require __DIR__.'/../app/controllers/StudyController.php';
    use App\Helpers\Auth;use App\Models\Study;
    $GLOBALS['checks']=0;$GLOBALS['db']=new Fixture();
    foreach(['draft','submitted','adviser_reviewed','pending','dean_review','under_review','library_review','admin_review','revision_requested','archived'] as $status){
        check(!Study::canDeletePublished(Auth::user(),['status'=>$status]),'No delete button for '.$status);
        $GLOBALS['db']->status=$status;
        rejects(fn()=>Study::deletePublished(5,Auth::user()),'Only published');
        check(!$GLOBALS['db']->deleted&&!$GLOBALS['db']->inTransaction(),'Nonpublished preserved');
    }
    $controller=new \App\Controllers\StudyController();
    foreach(['student','research_adviser','dept_research_coordinator','dean','school_research_coordinator','account_admin','librarian'] as $role){
        Auth::$actor['role_slug']=$role;
        check(!Study::canDeletePublished(Auth::user(),['status'=>'approved']),'Other role excluded');
        rejects(fn()=>Study::deletePublished(5,Auth::user()),'Only the Librarian');
        rejects(fn()=>$controller->deletePublishedSubmit(),'forbidden');
    }
    Auth::$actor['role_slug']='school_administrator';$GLOBALS['db']->status='approved';
    $GLOBALS['db']->race=true;rejects(fn()=>Study::deletePublished(5,Auth::user()),'study changed');check(!$GLOBALS['db']->deleted,'Concurrent change preserved');
    $GLOBALS['db']->race=false;\App\Helpers\Audit::$fail=true;
    rejects(fn()=>Study::deletePublished(5,Auth::user()),'audit failure');check(!$GLOBALS['db']->deleted&&!$GLOBALS['db']->removed,'Audit failure rolls deletion back');
    \App\Helpers\Audit::$fail=false;Study::deletePublished(5,Auth::user());
    check($GLOBALS['db']->deleted&&!$GLOBALS['db']->inTransaction(),'Published deletion committed');
    check(count($GLOBALS['db']->removed)===4,'Only this study files cleaned');
    check(!in_array('manuscripts/6/other.pdf',$GLOBALS['db']->removed,true),'Other study file preserved');
    check($GLOBALS['audit'][0]==='study.delete_published'&&$GLOBALS['audit'][3]['title']==='Published title','Deletion audited with title');
    echo 'Passed '.$GLOBALS['checks'].' published-study deletion checks.'.PHP_EOL;
}
