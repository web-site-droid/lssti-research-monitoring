<?php
$viewProgramCode = trim((string) ($viewProgramCode ?? ''));
$readsOwnDepartment = !empty($readsOwnDepartment);
$departments = [];
foreach ($programs ?? [] as $program) {
    $code = trim((string) ($program['code'] ?? ''));
    if ($code !== '') {
        $departments[$code] = $code;
    }
}
foreach (array_merge($signIns ?? [], $reads ?? [], $downloads ?? []) as $row) {
    foreach (['program_code', 'study_program_code'] as $key) {
        $code = trim((string) ($row[$key] ?? ''));
        if ($code !== '') {
            $departments[$code] = $code;
        }
    }
}
ksort($departments);
if ($viewProgramCode !== '' && !isset($departments[$viewProgramCode])) {
    $departments[$viewProgramCode] = $viewProgramCode;
    ksort($departments);
}
?>
<div class="page-header">
    <h1>Logs</h1>
    <p class="lead"><?php
        if ($readsOwnDepartment) {
            echo $viewProgramCode !== ''
                ? 'See who signed in, who read an article, and who downloaded a file from ' . e($viewProgramCode) . '.'
                : 'See who signed in, who read an article, and who downloaded a file from your department.';
        } else {
            echo 'See who signed in, who read an article, and who downloaded a file in every department.';
        }
    ?></p>
</div>
<section class="card card-elevated paper-group is-wide">
    <h2 class="section-title">Who signed in</h2>
    <div class="people-filters">
        <label class="table-search">Search sign-ins
            <input type="search" data-table-filter="#signins-table" placeholder="Name, ID no., role, or department">
        </label>
        <label class="table-search is-filter" for="logs-dept-filter">Department
            <select id="logs-dept-filter" data-table-dept="#signins-table" <?= $readsOwnDepartment ? 'disabled' : '' ?>>
                <?php if (!$readsOwnDepartment): ?><option value="">All departments</option><?php endif; ?>
                <?php foreach ($departments as $code): ?>
                    <option value="<?= e($code) ?>"<?= $viewProgramCode === $code ? ' selected' : '' ?>><?= e($code) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <p id="signins-table-empty" class="empty-state" hidden data-empty-dept="No sign-ins in that department." data-empty-text="No sign-ins match.">No sign-ins match.</p>
    <?php if (($signIns ?? []) === []): ?>
        <p class="empty-state">No one has signed in yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-sortable id="signins-table">
                <thead><tr><th>Name</th><th>ID no.</th><th>Role</th><th>Department</th><th>Last sign-in</th></tr></thead>
                <tbody>
                <?php foreach ($signIns as $row): ?>
                    <?php $programCode = trim((string) ($row['program_code'] ?? '')); ?>
                    <tr data-program="<?= e($programCode) ?>">
                        <td><?= e($row['last_name'] . ', ' . $row['first_name']) ?></td>
                        <td><?= e($row['school_id']) ?></td>
                        <td><?= e(\App\Helpers\Role::label('', (string) $row['role_name'])) ?></td>
                        <td><?= $programCode !== '' ? e($programCode) : 'All departments' ?></td>
                        <td><?= e(timestamp_label($row['last_login'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="card card-elevated paper-group is-wide">
    <h2 class="section-title">Who read an article<?= $readsOwnDepartment && $viewProgramCode !== '' ? ' — ' . e($viewProgramCode) : '' ?></h2>
    <p class="muted"><?= $readsOwnDepartment
        ? 'People who opened a full article from your department. The department column is the reader’s program.'
        : 'People who opened a full article in any department. Filter by the article’s department.' ?></p>
    <div class="people-filters">
        <label class="table-search">Search reads
            <input type="search" data-table-filter="#reads-table" placeholder="Name, ID no., or article">
        </label>
        <?php if (!$readsOwnDepartment): ?>
        <label class="table-search is-filter" for="logs-read-dept-filter">Article department
            <select id="logs-read-dept-filter" data-table-dept="#reads-table">
                <option value="">All departments</option>
                <?php foreach ($departments as $code): ?>
                    <option value="<?= e($code) ?>"><?= e($code) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
    </div>
    <p id="reads-table-empty" class="empty-state" hidden data-empty-dept="No reads in that department." data-empty-text="No reads match.">No reads match.</p>
    <?php if (($reads ?? []) === []): ?>
        <p class="empty-state"><?= $readsOwnDepartment ? 'No one has opened a full article from your department yet.' : 'No one has opened a full article yet.' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-sortable id="reads-table">
                <thead><tr><th>Name</th><th>ID no.</th><th>Role</th><th>Reader department</th><?php if (!$readsOwnDepartment): ?><th>Article department</th><?php endif; ?><th>Article</th><th>Read</th></tr></thead>
                <tbody>
                <?php foreach ($reads as $row): ?>
                    <?php
                    $readerCode = trim((string) ($row['program_code'] ?? ''));
                    $articleCode = trim((string) ($row['study_program_code'] ?? ''));
                    ?>
                    <tr data-program="<?= e($articleCode) ?>">
                        <td><?= e($row['last_name'] . ', ' . $row['first_name']) ?></td>
                        <td><?= e($row['school_id']) ?></td>
                        <td><?= e(\App\Helpers\Role::label('', (string) $row['role_name'])) ?></td>
                        <td><?= $readerCode !== '' ? e($readerCode) : 'All departments' ?></td>
                        <?php if (!$readsOwnDepartment): ?>
                            <td><?= $articleCode !== '' ? e($articleCode) : '—' ?></td>
                        <?php endif; ?>
                        <td><?php
                            $title = (string) ($row['title'] ?: 'Removed study');
                            $studyId = (int) ($row['study_id'] ?? 0);
                            if ($studyId > 0) {
                                echo '<a href="' . e(url('reports/read-log', ['study_id' => $studyId])) . '">' . e($title) . '</a>';
                            } else {
                                echo e($title);
                            }
                        ?></td>
                        <td><?= e(timestamp_label($row['opened_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="card card-elevated paper-group is-wide">
    <h2 class="section-title">Who downloaded a file<?= $readsOwnDepartment && $viewProgramCode !== '' ? ' — ' . e($viewProgramCode) : '' ?></h2>
    <p class="muted"><?= $readsOwnDepartment
        ? 'People who downloaded a full file from your department. The department column is the downloader’s program.'
        : 'People who downloaded a full file in any department. Filter by the article’s department.' ?></p>
    <div class="people-filters">
        <label class="table-search">Search downloads
            <input type="search" data-table-filter="#downloads-table" placeholder="Name, ID no., or file">
        </label>
        <?php if (!$readsOwnDepartment): ?>
        <label class="table-search is-filter" for="logs-download-dept-filter">Article department
            <select id="logs-download-dept-filter" data-table-dept="#downloads-table">
                <option value="">All departments</option>
                <?php foreach ($departments as $code): ?>
                    <option value="<?= e($code) ?>"><?= e($code) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
    </div>
    <p id="downloads-table-empty" class="empty-state" hidden data-empty-dept="No downloads in that department." data-empty-text="No downloads match.">No downloads match.</p>
    <?php if (($downloads ?? []) === []): ?>
        <p class="empty-state"><?= $readsOwnDepartment ? 'No one has downloaded a file from your department yet.' : 'No one has downloaded a file yet.' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table" data-sortable id="downloads-table">
                <thead><tr><th>Name</th><th>ID no.</th><th>Role</th><th>Downloader department</th><?php if (!$readsOwnDepartment): ?><th>Article department</th><?php endif; ?><th>File</th><th>Downloaded</th></tr></thead>
                <tbody>
                <?php foreach ($downloads as $row): ?>
                    <?php
                    $readerCode = trim((string) ($row['program_code'] ?? ''));
                    $articleCode = trim((string) ($row['study_program_code'] ?? ''));
                    ?>
                    <tr data-program="<?= e($articleCode) ?>">
                        <td><?= e($row['last_name'] . ', ' . $row['first_name']) ?></td>
                        <td><?= e($row['school_id']) ?></td>
                        <td><?= e(\App\Helpers\Role::label('', (string) $row['role_name'])) ?></td>
                        <td><?= $readerCode !== '' ? e($readerCode) : 'All departments' ?></td>
                        <?php if (!$readsOwnDepartment): ?>
                            <td><?= $articleCode !== '' ? e($articleCode) : '—' ?></td>
                        <?php endif; ?>
                        <td><?php
                            $title = (string) ($row['title'] ?: 'Removed study');
                            $studyId = (int) ($row['study_id'] ?? 0);
                            if ($studyId > 0) {
                                echo '<a href="' . e(url('reports/read-log', ['study_id' => $studyId])) . '">' . e($title) . '</a>';
                            } else {
                                echo e($title);
                            }
                        ?></td>
                        <td><?= e(timestamp_label($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
