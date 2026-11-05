<?php
declare(strict_types=1);
namespace App\Helpers {final class Auth {public static function user(): array {return ['id'=>1,'role_slug'=>'school_administrator','program_id'=>null];}}}
namespace App\Models {
    final class Program {public static function active(): array {return [];}}
    final class Study {public static function listAllSchoolWide(): array {
        $rows=[];foreach(['draft','submitted','pending','dean_review','under_review','admin_review','library_review','approved','archived'] as $i=>$status)$rows[]=['id'=>$i,'status'=>$status];return $rows;
    }}
}
namespace {
    final class Fixture {public function prepare($sql): self {return $this;}public function query($sql): self {return $this;}public function execute($params): void {}public function fetchColumn(): int {return 0;}}
    function db(): Fixture {return new Fixture();}
    function flash($name) {return null;}
    function view($name,$data): void {$GLOBALS['rendered']=$data;}
    require __DIR__.'/../app/helpers/Role.php';
    require __DIR__.'/../app/controllers/DashboardController.php';
    foreach(['','earlier'] as $catalog){
        $_GET=['catalog'=>$catalog];(new \App\Controllers\DashboardController())->index();$data=$GLOBALS['rendered'];
        if(array_column($data['directorStudies'],'status')!==['admin_review','library_review','approved'])throw new \RuntimeException('Incorrect Librarian dashboard scope');
        if(array_column($data['uploads'],'status')!==['admin_review','library_review'])throw new \RuntimeException('Incorrect pending queue');
        if($data['stats']['department_studies']!==3)throw new \RuntimeException('Count does not match table');
    }
    echo 'Passed 6 Librarian dashboard scope checks.'.PHP_EOL;
}
