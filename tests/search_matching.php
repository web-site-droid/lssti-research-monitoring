<?php
declare(strict_types=1);
require __DIR__.'/../app/models/Study.php';
final class SearchFixture {
    public array $calls=[];
    public function prepare($sql): SearchStatement {$stmt=new SearchStatement($sql);$this->calls[]=$stmt;return $stmt;}
}
final class SearchStatement {
    public array $params=[];
    public function __construct(public string $sql){}
    public function execute($params=[]): void {$this->params=array_merge($this->params,$params);}
    public function bindValue($key,$value,$type=null): void {$this->params[$key]=$value;}
    public function fetchAll(): array {return [];}
}
function db(): SearchFixture {return $GLOBALS['db'];}
$GLOBALS['db']=new SearchFixture();
\App\Models\Study::searchApproved(['q'=>'the AI']);$call=$GLOBALS['db']->calls[0];
if(($call->params['w0']??'')!=='%the%'||($call->params['w1']??'')!=='%AI%')throw new RuntimeException('Typed words were dropped');
if(!str_contains($call->sql,'LIKE :w0 AND ')||!str_contains($call->sql,'LIKE :w1'))throw new RuntimeException('Every typed word must match');
if(str_contains($call->sql,"IFNULL(s.abstract"))throw new RuntimeException('Unadvertised abstract matching');
if(!str_contains($call->sql,'ORDER BY (s.title LIKE :title_match) DESC')||$call->params['title_match']!=='%the AI%')throw new RuntimeException('Title relevance missing');
\App\Models\Study::searchApproved(['q'=>'the'],'date');$date=$GLOBALS['db']->calls[1];
if(isset($date->params['title_match'])||!str_contains($date->sql,'ORDER BY s.approved_at DESC'))throw new RuntimeException('Explicit date sorting changed');
\App\Models\Study::suggest('the%_');$suggest=$GLOBALS['db']->calls[2];
if($suggest->params[':t']!=='the\\%\\_%')throw new RuntimeException('Suggestion text not escaped literally');
\App\Models\Study::searchApproved(['q'=>'d']);$initial=$GLOBALS['db']->calls[5];
if(($initial->params['initial']??'')!=='d%'||!str_contains($initial->sql,'s.title LIKE :initial'))throw new RuntimeException('Single letter must match title prefix');
echo 'Passed 7 server search query checks.'.PHP_EOL;
