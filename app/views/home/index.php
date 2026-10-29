<?php $pageTitle = APP_NAME; ?>
<main class="scholar-main" id="main">
    <div class="scholar-hero">
        <h1 class="scholar-wordmark">
            <span class="wm-l">L</span><span class="wm-s">S</span><span class="wm-s2">S</span><span class="wm-t">T</span><span class="wm-i">I</span>
            <span class="wm-research">Research</span>
        </h1>
        <?php
        $studentLocked = !empty($studentLocked) && (int) ($studentProgramId ?? 0) > 0;
        $studentProgramLabel = trim((string) ($studentProgramLabel ?? ''), ' —');
        ?>
        <?php if ($studentLocked): ?>
            <p class="muted scholar-dept-lock">Searching <?= e($studentProgramLabel !== '' ? $studentProgramLabel : 'your department') ?> only</p>
        <?php endif; ?>
        <form class="scholar-search" role="search" action="<?= e(url('')) ?>" method="get" onsubmit="return false;">
            <label class="visually-hidden" for="q">Search studies</label>
            <input type="search" id="q" name="q" placeholder="<?= $studentLocked ? 'Search your department titles, authors, or objectives' : 'Search titles, authors, or objectives' ?>" autocomplete="off" aria-autocomplete="list" aria-controls="suggestions">
            <button type="button" id="search-go" aria-label="Search"><?= icon('search') ?> Search</button>
        </form>
        <ul id="suggestions" class="suggestions scholar-suggestions" role="listbox"></ul>
    </div>
    <?php
    $canRate = $user && ($user['role_slug'] ?? '') === 'student';
    $filterYear = (int) date('Y');
    $sinceYears = [$filterYear, $filterYear - 1, $filterYear - 4];
    ?>
    <div class="scholar-board">
        <aside id="scholar-side" class="scholar-side" aria-label="Filter studies">
            <input type="hidden" id="date_field" value="approval">
            <input type="hidden" id="date_from" value="">
            <input type="hidden" id="date_to" value="">
            <input type="hidden" id="sort" value="relevance">
            <div class="scholar-side-group">
                <button type="button" data-time="any" class="is-on">Any time</button>
                <?php foreach ($sinceYears as $since): ?>
                    <button type="button" data-time="<?= (int) $since ?>">Since <?= (int) $since ?></button>
                <?php endforeach; ?>
                <button type="button" data-time="custom">Custom range...</button>
                <div id="scholar-range" class="scholar-range" hidden>
                    <label>From <input type="date" id="range_from"></label>
                    <label>To <input type="date" id="range_to"></label>
                </div>
            </div>
            <div class="scholar-side-group scholar-side-fields">
                <label for="study_type">Type</label>
                <select id="study_type">
                    <option value="">Any type</option>
                    <?php foreach (study_type_options() as $typeValue => $typeLabel): ?>
                        <option value="<?= e($typeValue) ?>"><?= e($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="program_id">Department</label>
                <?php if ($studentLocked): ?>
                    <input type="hidden" id="program_id" name="program_id" value="<?= (int) $studentProgramId ?>">
                    <p class="muted" style="margin:0.25rem 0 0"><?= e($programs[0]['code'] ?? ($user['program_code'] ?? 'Your department')) ?> only</p>
                <?php else: ?>
                <select id="program_id">
                    <option value="">Any department</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?= (int) $program['id'] ?>"><?= e($program['code']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <label for="adviser_user_id">Adviser</label>
                <select id="adviser_user_id">
                    <option value="">Any adviser</option>
                    <?php foreach ($advisers as $adviser): ?>
                        <option value="<?= (int) $adviser['id'] ?>"><?= e($adviser['last_name'] . ', ' . $adviser['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="school_year">Year</label>
                <select id="school_year">
                    <option value="">Any year</option>
                    <?php foreach ($schoolYears as $year): ?>
                        <option value="<?= e($year) ?>"><?= e($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </aside>
        <div id="results" class="results scholar-results" aria-live="polite" data-can-rate="<?= $canRate ? '1' : '0' ?>" data-logged="<?= $user ? '1' : '0' ?>" data-csrf="<?= e(\App\Helpers\Csrf::token()) ?>"<?= $studentLocked ? ' data-dept-locked="1"' : '' ?>></div>
    </div>
</main>
<dialog id="cite-modal" class="preview-modal" aria-labelledby="cite-title">
    <div class="preview-modal-head">
        <h2 id="cite-title">Cite</h2>
        <button type="button" id="cite-close" class="btn btn-secondary btn-icon-only" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div id="cite-body"></div>
</dialog>
<dialog id="quick-modal" class="preview-modal" aria-labelledby="quick-title">
    <div class="preview-modal-head">
        <h2 id="quick-title">Quick read</h2>
        <button type="button" id="quick-close" class="btn btn-secondary btn-icon-only" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div id="quick-body"></div>
</dialog>
<?php $pageScripts = ['search.js']; ?>
