<?php
$studentLocked = !empty($studentLocked) && (int) ($studentProgramId ?? 0) > 0;
$studentProgramLabel = trim((string) ($studentProgramLabel ?? ''), ' —');
?>
<div class="page-header">
    <h1>Search approved studies</h1>
    <p class="lead">Type to search instantly. Use filters and date range to narrow results.</p>
    <?php if ($studentLocked): ?>
        <p class="muted scholar-dept-lock">Searching <?= e($studentProgramLabel !== '' ? $studentProgramLabel : 'your department') ?> only</p>
    <?php endif; ?>
</div>
<div class="search-panel">
    <label for="q">Search</label>
    <div class="search-row" style="display:flex; gap:.75rem; align-items:center;">
        <input type="search" id="q" name="q" autocomplete="off" aria-autocomplete="list" aria-controls="suggestions" placeholder="<?= $studentLocked ? 'Search your department titles, authors, or objectives' : 'Search titles, authors, or keywords' ?>" style="flex:1;">
        <button type="button" id="search-go" class="btn btn-primary">Search</button>
    </div>
    <ul id="suggestions" class="suggestions" role="listbox"></ul>
    <div class="filters grid two">
        <label for="program_id">Program</label>
        <?php if ($studentLocked): ?>
            <input type="hidden" id="program_id" name="program_id" value="<?= (int) $studentProgramId ?>">
            <p class="muted" style="margin:0"><?= e($programs[0]['code'] ?? 'Your department') ?> only</p>
        <?php else: ?>
        <select id="program_id">
            <option value="">All</option>
            <?php foreach ($programs as $p): ?>
                <option value="<?= (int) $p['id'] ?>" data-code="<?= e($p['code']) ?>"><?= e($p['code']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label for="school_year">School year</label>
        <select id="school_year">
            <option value="">All</option>
            <?php foreach ($schoolYears as $year): ?>
                <option value="<?= e($year) ?>"><?= e($year) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="adviser_user_id">Adviser</label>
        <select id="adviser_user_id">
            <option value="">All</option>
            <?php foreach ($advisers as $a): ?>
                <option value="<?= (int) $a['id'] ?>"><?= e($a['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="study_type">Study type</label>
        <select id="study_type">
            <option value="">All</option>
            <?php foreach (study_type_options() as $typeValue => $typeLabel): ?>
                <option value="<?= e($typeValue) ?>"><?= e($typeLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="keyword">Keyword</label>
        <input id="keyword">
        <label for="date_field">Date field</label>
        <select id="date_field">
            <option value="submission">Submission</option>
            <option value="approval">Approval</option>
            <option value="defense">Defense</option>
        </select>
        <label for="date_from">From</label>
        <input type="date" id="date_from">
        <label for="date_to">To</label>
        <input type="date" id="date_to">
        <label for="sort">Sort</label>
        <select id="sort">
            <option value="relevance">Relevance</option>
            <option value="date">Date</option>
            <option value="title">Title</option>
        </select>
    </div>
</div>
<div id="results" class="results" aria-live="polite"<?= $studentLocked ? ' data-dept-locked="1"' : '' ?>></div>
<?php $pageScripts = ['search.js']; ?>
