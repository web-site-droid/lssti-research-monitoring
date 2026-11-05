<?php
declare(strict_types=1);

namespace App\Helpers {
    final class Auth {
        public static function user(): array { return ['id'=>99, 'role_slug'=>'student', 'program_id'=>1]; }
    }
}

namespace {
    require __DIR__.'/../app/helpers/Role.php';
    require __DIR__.'/../app/models/Study.php';
    require __DIR__.'/../app/controllers/SearchController.php';

    final class SearchResponse extends \RuntimeException {
        public function __construct(public array $payload) { parent::__construct('Response captured'); }
    }
    final class PerformanceDatabase {
        public array $calls = [];
        public array $papers = [];
        public array $authors = [];
        public function prepare(string $sql): PerformanceStatement {
            $stmt = new PerformanceStatement($this, $sql);
            $this->calls[] = $stmt;
            return $stmt;
        }
    }
    final class PerformanceStatement {
        public array $params = [];
        public function __construct(private PerformanceDatabase $db, public string $sql) {}
        public function execute(array $params = []): void { $this->params = $params; }
        public function fetchAll(): array {
            if (str_contains($this->sql, 'SELECT s.*')) return $this->db->papers;
            if (str_contains($this->sql, 'SELECT * FROM study_authors')) {
                $rows = array_values(array_filter($this->db->authors, fn ($row) => in_array($row['study_id'], $this->params, true)));
                usort($rows, fn ($a, $b) => [$a['study_id'], $a['sort_order']] <=> [$b['study_id'], $b['sort_order']]);
                return $rows;
            }
            if (str_contains($this->sql, 'FROM study_bookmarks')) return [['study_id'=>1]];
            if (str_contains($this->sql, 'SELECT study_id, stars')) return [['study_id'=>1, 'stars'=>4]];
            return [];
        }
    }
    function db(): PerformanceDatabase { return $GLOBALS['db']; }
    function json_response(array $payload): void { throw new SearchResponse($payload); }
    function publication_timestamp_label($stamp): string { return (string) $stamp; }
    function study_type_label($type): string { return (string) $type; }
    function check(bool $ok, string $message): void { if (!$ok) throw new \RuntimeException($message); }

    $queryCounts = [];
    foreach ([1, 13, 100] as $size) {
        $GLOBALS['db'] = new PerformanceDatabase();
        $fixture = db();
        for ($id=1; $id <= $size; $id++) {
            $fixture->papers[] = ['id'=>$id, 'title'=>'Reporter study '.$id, 'short_title'=>'Reporter',
                'program_id'=>$id === 1 ? 1 : 2, 'program_code'=>$id === 1 ? 'BSCS' : 'OTHER',
                'school_year'=>'2025-2026', 'approved_at'=>'2026-11-06 09:00:00', 'status'=>'approved',
                'study_type'=>'thesis', 'abstract'=>'Reporter abstract', 'research_objectives'=>'Reporter objective',
                'keyword_text'=>'reporter', 'current_version_id'=>$id+100, 'original_filename'=>'paper.pdf'];
            // Deliberately supply authors out of order to verify the SQL ordering contract.
            $fixture->authors[] = ['study_id'=>$id, 'last_name'=>'Second', 'first_name'=>'Ben', 'middle_name'=>'', 'sort_order'=>2];
            $fixture->authors[] = ['study_id'=>$id, 'last_name'=>'First', 'first_name'=>'Ana', 'middle_name'=>'Maria', 'sort_order'=>1];
        }
        $_GET = ['q'=>'reporter', 'full_text'=>'1'];
        try { (new \App\Controllers\SearchController())->api(); throw new \RuntimeException('Missing response'); }
        catch (SearchResponse $response) { $items = $response->payload['results']; }
        check(count($items) === $size, 'Result count changed');
        $authorQueries = array_values(array_filter($fixture->calls, fn ($stmt) => str_starts_with($stmt->sql, 'SELECT * FROM study_authors')));
        check(count($authorQueries) === 1, 'Search must use one author query regardless of result count');
        check(count($authorQueries[0]->params) === $size, 'Author batch must contain all result IDs');
        check(str_ends_with($authorQueries[0]->sql, 'ORDER BY study_id, sort_order'), 'Author order must be preserved by SQL');
        foreach ($items as $item) {
            check($item['authors'] === 'First, Ana M.; Second, Ben', 'Display names changed');
            $expected = \App\Models\Study::citations($fixture->papers[$item['id']-1], [
                ['last_name'=>'First','first_name'=>'Ana','middle_name'=>'Maria'],
                ['last_name'=>'Second','first_name'=>'Ben','middle_name'=>''],
            ]);
            check($item['citations'] === $expected, 'Citation content or author order changed');
            check($item['full_access'] === ($item['id'] === 1), 'Department access changed');
            check($item['version_id'] === ($item['id'] === 1 ? 101 : 0), 'Restricted version leaked');
            check($item['saved'] === ($item['id'] === 1), 'Personal favorite changed');
            check($item['my_stars'] === ($item['id'] === 1 ? 4 : 0), 'Rating changed');
        }
        $queryCounts[] = count($fixture->calls);
    }
    check(count(array_unique($queryCounts)) === 1, 'Database round trips must not grow with result count');
    $GLOBALS['db'] = new PerformanceDatabase();
    $_GET = ['q'=>'no match', 'full_text'=>'1'];
    try { (new \App\Controllers\SearchController())->api(); }
    catch (SearchResponse $response) { check($response->payload['results'] === [], 'Empty search changed'); }
    check(count(array_filter(db()->calls, fn ($stmt) => str_starts_with($stmt->sql, 'SELECT * FROM study_authors'))) === 0, 'Empty search must not fetch authors');
    check(\App\Models\Study::formatAuthors([]) === '', 'Missing authors should remain empty');
    echo 'Search performance passed: 1, 13, and 100 results each use '.$queryCounts[0].' database calls; names, citations, stars, and department access preserved.'.PHP_EOL;
}
