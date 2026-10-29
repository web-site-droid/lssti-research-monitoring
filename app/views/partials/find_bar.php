<?php
$table = (string) ($table ?? '');
$cards = (string) ($cards ?? '');
$pager = (string) ($pager ?? '');
$empty = (string) ($empty ?? '');
$papers = $papers ?? [];
$programs = $programs ?? [];
$statusOptions = $statusOptions ?? [];
$statusAttr = (string) ($statusAttr ?? 'data-gate');
$pageSize = (int) ($pageSize ?? 0);
$searchId = (string) ($searchId ?? '');
$mode = (string) ($mode ?? '');
$departments = [];
foreach ($programs as $program) {
    $code = is_array($program) ? (string) ($program['code'] ?? '') : (string) $program;
    if ($code !== '') {
        $departments[$code] = $code;
    }
}
$years = [];
$types = study_type_options();
foreach ($papers as $paper) {
    $code = (string) ($paper['program_code'] ?? '');
    if ($code !== '') {
        $departments[$code] = $code;
    }
    $year = (string) ($paper['school_year'] ?? '');
    if ($year !== '') {
        $years[$year] = $year;
    }
    $type = (string) ($paper['study_type'] ?? '');
    if ($type !== '' && !isset($types[$type])) {
        $types[$type] = study_type_label($type);
    }
}
ksort($departments);
krsort($years);
?>
<div class="find-board"
    data-find-bar
    data-find-table="<?= e($table) ?>"
    <?php if ($cards !== ''): ?>data-find-cards="<?= e($cards) ?>"<?php endif; ?>
    <?php if ($pager !== ''): ?>data-find-pager="<?= e($pager) ?>"<?php endif; ?>
    <?php if ($empty !== ''): ?>data-find-empty="<?= e($empty) ?>"<?php endif; ?>
    <?php if ($mode !== ''): ?>data-find-mode="<?= e($mode) ?>"<?php endif; ?>
    data-status-attr="<?= e($statusAttr) ?>"
    data-page-size="<?= $pageSize ?>">
    <div class="find-summary-bar">
        <div class="find-summary">
            <span class="find-mark" aria-hidden="true"><?= icon('book') ?></span>
            <strong data-find-count>0 results</strong>
            <span class="find-page" data-find-page>Page 1 of 1</span>
        </div>
        <div class="find-tools">
            <label class="find-search">
                <span class="visually-hidden">Search</span>
                <input type="search" data-find-q <?= $searchId !== '' ? 'id="' . e($searchId) . '" data-manual' : '' ?> placeholder="Search title, authors, or department" autocomplete="off" aria-autocomplete="list" aria-controls="find-hits-<?= e(ltrim($table, '#')) ?>">
                <div class="find-hits" id="find-hits-<?= e(ltrim($table, '#')) ?>" data-find-hits hidden></div>
            </label>
            <label class="find-sort">
                <span class="visually-hidden">Sort</span>
                <select data-find-sort aria-label="Sort">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="title">Title A–Z</option>
                    <option value="title-desc">Title Z–A</option>
                </select>
            </label>
            <button type="button" class="find-toggle is-on" data-find-toggle aria-expanded="true"><?= icon('filter') ?> Filters</button>
        </div>
    </div>
    <div class="find-panel" data-find-panel>
        <div class="find-fields">
            <label class="find-field">
                <span><?= icon('home') ?> Department</span>
                <select data-find-dept aria-label="Department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $code): ?>
                        <option value="<?= e($code) ?>"><?= e($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="find-field">
                <span><?= icon('file') ?> Paper Type</span>
                <select data-find-type aria-label="Paper type">
                    <option value="">All Types</option>
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="find-field find-field-range">
                <span><?= icon('calendar') ?> Date Range</span>
                <span class="find-range">
                    <input type="month" data-find-from aria-label="From month">
                    <span>to</span>
                    <input type="month" data-find-to aria-label="To month">
                </span>
            </label>
            <label class="find-field">
                <span><?= icon('calendar') ?> Publication Year</span>
                <select data-find-year aria-label="Publication year">
                    <option value="">Any year</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= e($year) ?>"><?= e($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($statusOptions): ?>
            <label class="find-field">
                <span><?= icon('flag') ?> Status</span>
                <select data-find-status aria-label="Status">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= e((string) $value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </div>
        <div class="find-actions">
            <button type="button" class="find-apply" data-find-apply><?= icon('check') ?> Apply</button>
            <button type="button" class="find-reset" data-find-reset><?= icon('refresh') ?> Reset</button>
        </div>
    </div>
</div>
