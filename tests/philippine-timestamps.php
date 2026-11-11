<?php
declare(strict_types=1);

namespace App\Helpers {
    final class Auth { public static function user(): array { return $GLOBALS['user']; } }
}
namespace App\Models {
    final class Study {
        public static function listForUser($user): array { return []; }
        public static function canOpenFullManuscript($user, $study): bool { return false; }
        public static function hasLibraryReview(int $id): bool { return false; }
    }
}
namespace {
    define('DB_TIMEZONE', '+00:00'); // Hosted database fixtures.
    require dirname(__DIR__) . '/config/config.php';
    require APP_PATH . '/helpers/functions.php';
    require APP_PATH . '/helpers/Role.php';
    require APP_PATH . '/helpers/Workflow.php';
    $checks = 0;
    function check(bool $passed, string $message): void {
        $GLOBALS['checks']++;
        if (!$passed) throw new \RuntimeException($message);
    }
    check(date_default_timezone_get() === 'Asia/Manila', 'Application calendar must use Manila');
    check(timestamp_label('2026-11-11 01:00:00') === 'November 11, 2026 at 9:00 AM PHT', 'Reported 1 AM must display as 9 AM PHT');
    check(timestamp_label('2026-11-11 20:30:00') === 'November 12, 2026 at 4:30 AM PHT', 'UTC midnight boundary');
    foreach ([null, '', '2026-02-30 01:00:00', 'invalid'] as $invalid) {
        check(timestamp_label($invalid) === 'Date unavailable', 'Invalid timestamp must not become a real date');
    }
    // Conversions must not depend on the host's configured timezone.
    date_default_timezone_set('America/Los_Angeles');
    check(timestamp_label('2026-11-11 01:00:00') === 'November 11, 2026 at 9:00 AM PHT', 'Timezone independence');
    date_default_timezone_set('Asia/Manila');
    $paper = [
        'id'=>1, 'title'=>'Timestamp fixture', 'short_title'=>'Fixture', 'authors'=>'Sample Author',
        'abstract'=>'Sample abstract', 'research_objectives'=>'', 'program_code'=>'BSCS',
        'school_year'=>'2026-2027', 'study_type'=>'thesis', 'status'=>'approved',
        'current_version_id'=>0, 'submitter_first'=>'Sample', 'submitter_last'=>'Student',
        'upload_date'=>'2026-11-09', 'created_at'=>'2026-11-11 01:00:00', 'approved_at'=>'2026-11-11 20:30:00',
    ];
    check(upload_date_label($paper) === 'November 9, 2026', 'Selected calendar date must remain unchanged');
    check(upload_date_label(['created_at'=>'2026-11-11 20:30:00']) === 'November 12, 2026', 'Legacy date fallback must use PHT');
    foreach (['school_administrator','school_research_coordinator','librarian','research_adviser','dean','dept_research_coordinator'] as $role) {
        $user = ['id'=>1, 'first_name'=>'Staff', 'role_slug'=>$role, 'program_id'=>1];
        $directorStudies = $deptCatalog = $otherDeptCatalog = [$paper];
        $programs = $stats = $uploads = [];
        $success = $error = null;
        $departmentLabel = 'BSCS';
        ob_start(); require APP_PATH . '/views/dashboard/index.php'; $html = ob_get_clean();
        check(str_contains($html, 'Upload date: November 9, 2026'), $role . ' selected date missing');
        check(str_contains($html, 'Recorded: November 11, 2026 at 9:00 AM PHT'), $role . ' timestamp missing');
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);
        foreach ($doc->getElementsByTagName('table') as $table) {
            $rows = $table->getElementsByTagName('tr');
            $columns = $rows->item(0)->getElementsByTagName('th')->length;
            foreach ($rows as $i=>$row) {
                if ($i > 0) check($row->getElementsByTagName('td')->length === $columns, 'Dashboard column alignment');
            }
        }
        $directorStudies = $deptCatalog = $otherDeptCatalog = [];
        ob_start(); require APP_PATH . '/views/dashboard/index.php'; $empty = ob_get_clean();
        check(!str_contains($empty, 'Recorded:'), 'Empty dashboards must not invent timestamps');
    }
    ob_start(); partial('paper_read_card', ['paper'=>$paper]); $card = ob_get_clean();
    check(str_contains($card, '9:00 AM PHT'), 'Reading card timestamp missing');
    $studies = [$paper]; $_SESSION = [];
    ob_start(); require APP_PATH . '/views/studies/track.php'; $tracking = ob_get_clean();
    check(str_contains($tracking, 'November 12, 2026</h2>'), 'Track grouping must use the Philippine calendar day');
    check(str_contains($tracking, 'November 12, 2026 at 4:30 AM PHT'), 'Track timestamp must agree with heading');
    echo 'Passed ' . $checks . " Philippine timestamp checks.\n";
}
