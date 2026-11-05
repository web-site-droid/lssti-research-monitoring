<?php
declare(strict_types=1);
define('APP_PATH',dirname(__DIR__).'/app');
require APP_PATH.'/helpers/functions.php';
require APP_PATH.'/helpers/Workflow.php';
$_SESSION=[];
$studies=[
    ['id'=>1,'status'=>'approved','title'=>'Older publication','short_title'=>'Old','study_type'=>'thesis','approved_at'=>'2025-10-02 08:00:00'],
    ['id'=>2,'status'=>'approved','title'=>'Newest publication','short_title'=>'New','study_type'=>'thesis','approved_at'=>'2026-11-06 14:30:00'],
    ['id'=>3,'status'=>'approved','title'=>'Earlier same day','short_title'=>'Earlier','study_type'=>'thesis','approved_at'=>'2026-11-06 09:00:00'],
    ['id'=>4,'status'=>'approved','title'=>'Unknown publication date','short_title'=>'Unknown','study_type'=>'thesis','approved_at'=>null],
];
ob_start();require APP_PATH.'/views/studies/track.php';$html=ob_get_clean();
if(!(strpos($html,'Newest publication')<strpos($html,'Earlier same day')&&strpos($html,'Earlier same day')<strpos($html,'Older publication')))throw new RuntimeException('Publication ordering wrong');
if(substr_count($html,'November 6, 2026</h2>')!==1)throw new RuntimeException('Same-day publications must be grouped');
if(!str_contains($html,'November 6, 2026 at 2:30 PM'))throw new RuntimeException('Date and time missing');
if(substr_count($html,'progress-step-result">Finished')!==4)throw new RuntimeException('Publish must be finished for each published paper');
if(substr_count($html,'is-complete')!==28)throw new RuntimeException('All published steps must be completed');
if(!str_contains($html,'Publication date unavailable'))throw new RuntimeException('Missing dates must not be invented');
if(str_contains($html,'data-panel='))throw new RuntimeException('Tracking must appear together without separate panels');
$studies[]=['id'=>5,'status'=>'admin_review','title'=>'Awaiting librarian','study_type'=>'thesis','updated_at'=>'2026-11-06 15:00:00'];
ob_start();require APP_PATH.'/views/studies/track.php';$mixed=ob_get_clean();
if(!str_contains($mixed,'Awaiting librarian')||!str_contains($mixed,'Newest publication')||substr_count($mixed,'class="progress-track"')!==5)throw new RuntimeException('Published and active trackers must display together');
$study=['status'=>'admin_review'];$skipAdviser=false;ob_start();require APP_PATH.'/views/partials/study_progress.php';$pending=ob_get_clean();
if(str_contains($pending,'>Finished</span>')||substr_count($pending,'is-current')!==1)throw new RuntimeException('Pending paper incorrectly marked finished');
$study=['status'=>'approved'];$skipAdviser=true;ob_start();require APP_PATH.'/views/partials/study_progress.php';$short=ob_get_clean();
if(substr_count($short,'is-complete')!==6||!str_contains($short,'>Finished</span>'))throw new RuntimeException('Alternate track completion wrong');
echo 'Passed 10 publication tracking checks.'.PHP_EOL;
