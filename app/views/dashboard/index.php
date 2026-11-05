<?php $pageTitle = 'Home'; ?>
<?php if (($user['role_slug'] ?? '') !== 'student'): ?>
<div class="page-header">
    <h1>Welcome, <?= e($user['first_name']) ?></h1>
    <p class="lead"><?php
        $roleSlug = $user['role_slug'] ?? '';
        if ($roleSlug === 'dept_research_coordinator') {
            echo 'Open full files for every paper in your department. Other departments show title and abstract only.';
        } elseif ($roleSlug === 'dean') {
            echo 'Open full files for every paper in your department. Other departments show title and abstract only.';
        } elseif ($roleSlug === 'school_research_coordinator') {
            echo 'View every research paper from all departments with full manuscript access. Complete ethics and plagiarism, then send cleared studies to the librarian.';
        } elseif ($roleSlug === 'librarian') {
            echo 'View every research paper from all departments with full manuscript access.';
        } elseif ($roleSlug === 'school_administrator') {
            echo 'View every research paper from all departments with full manuscript access. Publish studies cleared by the school research coordinator, and create dean and research coordinator accounts.';
        } elseif ($roleSlug === 'research_adviser') {
            echo 'Open full files for every paper in your department. Other departments show title and abstract only.';
        } elseif ($roleSlug === 'student') {
            $dept = trim((string) ($departmentLabel ?? ''), ' —');
            echo $dept !== ''
                ? 'Published papers in ' . e($dept) . ' are listed here. You can also search other departments. Students see the title and abstract only — never the full file.'
                : 'Published papers in your department are listed here. You can also search other departments. Students see the title and abstract only — never the full file.';
        } else {
            echo 'Choose what you want to do next.';
        }
    ?></p>
</div>
<?php endif; ?>

<?php if (!empty($stats) && ($user['role_slug'] ?? '') !== 'student'): ?>
<section class="stat-grid" aria-label="Quick counts">
    <?php foreach ($stats as $key => $val): ?>
        <?php
        $statHref = ($user['role_slug'] ?? '') === 'research_adviser' ? match ($key) {
            'pending' => url('review', ['decision' => 'pending']),
            'accepted' => url('review', ['decision' => 'accepted']),
            'department_studies' => '#adviser-department',
            'other_studies' => '#adviser-discover',
            default => null,
        } : null;
        if (($user['role_slug'] ?? '') === 'school_administrator') {
            $statHref = match ($key) {
                'pending' => url('review', ['decision' => 'pending']),
                'accepted' => url('review', ['decision' => 'accepted']),
                'department_studies' => url('dashboard') . '#dash-studies',
                'other_studies' => url('study'),
                default => null,
            };
        }
        if (($user['role_slug'] ?? '') === 'school_research_coordinator') {
            $statHref = match ($key) {
                'pending' => url('review', ['decision' => 'pending']),
                'accepted' => url('review', ['decision' => 'accepted']),
                'department_studies' => '#dash-studies',
                'other_studies' => '#dash-studies',
                default => null,
            };
        }
        if (($user['role_slug'] ?? '') === 'dean') {
            $statHref = match ($key) {
                'pending' => url('review', ['decision' => 'pending']),
                'accepted' => url('review', ['decision' => 'accepted']),
                'department_studies' => '#dean-dept-table',
                'other_studies' => '#dean-other-table',
                default => null,
            };
        }
        if (($user['role_slug'] ?? '') === 'dept_research_coordinator') {
            $statHref = match ($key) {
                'pending' => url('library', ['decision' => 'pending']),
                'accepted' => url('library', ['decision' => 'accepted']),
                'department_studies' => '#drc-dept-table',
                'other_studies' => '#drc-other-table',
                default => null,
            };
        }
        ?>
        <?php if ($statHref): ?><a class="stat-card stat-card-link" href="<?= e($statHref) ?>"><?php else: ?><div class="stat-card"><?php endif; ?>
            <span class="stat-value"><?= e((string) $val) ?></span>
            <span class="stat-key"><?php
                $statLabels = [
                    'vet' => 'Waiting',
                    'review' => 'To check',
                    'pending' => 'Pending',
                    'accepted' => 'Accepted',
                    'department_studies' => 'All studies',
                    'other_studies' => 'Other studies',
                    'other_departments' => 'Other depts',
                    'studies' => 'Studies',
                    'pending_studies' => 'To check',
                    'approved_studies' => 'Approved',
                    'my_studies' => 'My studies',
                    'approved' => 'Approved',
                ];
                echo e($statLabels[$key] ?? ucwords(str_replace('_', ' ', $key)));
            ?></span>
        <?php if ($statHref): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (in_array($user['role_slug'] ?? '', ['school_administrator', 'school_research_coordinator', 'librarian'], true)): ?>
<?php
$roleDash = $user['role_slug'] ?? '';
$isSrcDash = $roleDash === 'school_research_coordinator';
$isLibDash = $roleDash === 'librarian';
$isAdminDash = $roleDash === 'school_administrator';
$isSchoolWideFull = $isSrcDash || $isLibDash || $isAdminDash;
$schoolWideEmpty = 'No research papers yet.';
$schoolWideFilterEmpty = 'No papers in this department.';
?>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<section class="card card-elevated">
    <h2 class="section-title"><?= $isAdminDash ? 'Librarian review & published studies' : 'All departments — full manuscripts' ?></h2>
    <?php if ($isSrcDash): ?>
        <p class="muted">Every research paper school-wide. Full files are available. Open review for ethics, plagiarism, and send to the librarian.</p>
    <?php elseif ($isLibDash): ?>
        <p class="muted">Every research paper school-wide. Full files are available.</p>
    <?php elseif ($isAdminDash): ?>
        <p class="muted">All departments: studies awaiting Librarian review and approved publications. Full manuscripts are available.</p>
    <?php endif; ?>
<?php partial('find_bar', [
    'table' => '#dash-studies',
    'empty' => '#dash-studies-empty',
    'papers' => $directorStudies,
    'programs' => $programs,
    'statusOptions' => ['' => 'Any status', 'pending' => 'Pending', 'accepted' => 'Accepted'],
]); ?>
<p id="dash-studies-empty" class="empty-state" hidden data-empty-dept="No papers in that department." data-empty-text="No studies match.">No studies match.</p>
<div class="table-scroll<?= count($directorStudies) > 10 ? ' is-scroll' : '' ?>">
<div class="table-wrap dash-board">
<table class="dashboard-study-table dash-table src-study-table" id="dash-studies" data-sortable>
    <thead>
        <tr>
            <th>Title</th>
            <th>Authors</th>
            <th>Department</th>
            <th>Status</th>
            <th data-nosort>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$directorStudies): ?>
        <tr class="dash-empty-row"><td><?= e($schoolWideEmpty) ?></td><td></td><td></td><td></td><td></td></tr>
    <?php else: ?>
        <?php foreach ($directorStudies as $study): ?>
            <?php
            $canSendLibrary = $isLibDash
                && ($study['status'] ?? '') === 'library_review'
                && \App\Models\Study::hasLibraryReview((int) $study['id']);
            $canFinalPublish = $isAdminDash && in_array($study['status'] ?? '', ['admin_review', 'library_review'], true);
            $studyStatus = (string) ($study['status'] ?? '');
            $pendingGate = $isSrcDash ? ['under_review'] : ($isLibDash ? ['library_review'] : ['admin_review', 'library_review']);
            $acceptedGate = $isSrcDash
                ? ['library_review', 'admin_review', 'approved', 'archived']
                : ($isLibDash ? ['admin_review', 'approved', 'archived'] : ['approved', 'archived']);
            $studyGate = in_array($studyStatus, $pendingGate, true)
                ? 'pending'
                : (in_array($studyStatus, $acceptedGate, true) ? 'accepted' : '');
            ?>
            <tr<?= paper_find_attrs($study) ?> data-gate="<?= e($studyGate) ?>">
                <td><?= e($study['title']) ?></td>
                <td><?= author_cell($study['authors'] ?? '', (string) $study['title']) ?></td>
                <td><?= e($study['program_code']) ?></td>
                <td><?= status_badge((string) $study['status']) ?></td>
                <td class="actions-cell">
                    <?php
                    ob_start();
                    echo '<button type="button" class="btn btn-secondary btn-icon-only" data-study-quick'
                        . ' data-title="' . e($study['title']) . '"'
                        . ' data-meta="' . e(trim(($study['authors'] ?: '—') . ' · ' . ($study['program_code'] ?? '') . ' · ' . ($study['school_year'] ?? ''), ' ·')) . '"'
                        . ' data-abstract="' . e($study['abstract'] ?? '') . '"'
                        . ' data-objectives="' . e($study['research_objectives'] ?? '') . '"'
                        . ' aria-label="View" title="View">' . icon('eye') . '</button>';
                    if ($isSrcDash && ($study['status'] ?? '') === 'under_review') {
                        echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('review/ethics', ['id' => $study['id']])) . '" aria-label="Open review" title="Open review">' . icon('check') . '</a>';
                    }
                    if ($isAdminDash && in_array($study['status'] ?? '', ['admin_review', 'library_review'], true)) {
                        echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('review/ethics', ['id' => $study['id']])) . '" aria-label="Open review" title="Open review">' . icon('check') . '</a>';
                    }
                    if ($isLibDash && ($study['status'] ?? '') === 'library_review') {
                        echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('library/review', ['id' => $study['id']])) . '" aria-label="Open library review" title="Open library review">' . icon('check') . '</a>';
                    }
                    if (can_open_full_file($user, $study)) {
                        echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reader', ['id' => $study['id']])) . '" aria-label="View full file" title="View full file">' . icon('book') . '</a>';
                        echo '<a class="btn btn-green btn-icon-only" href="' . e(url('file/manuscript', [
                            'study_id' => $study['id'],
                            'version_id' => $study['current_version_id'],
                        ])) . '" aria-label="Download full file" title="Download full file">' . icon('download') . '</a>';
                    }
                    if ($isAdminDash) {
                        echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reports/read-log', ['study_id' => $study['id']])) . '" aria-label="Who read this article" title="Who read this article">' . icon('list') . '</a>';
                    }
                    if ($isSrcDash && ($study['status'] ?? '') === 'under_review') {
                        echo '<form method="post" action="' . e(url('review/approve')) . '" class="inline-form">'
                            . \App\Helpers\Csrf::field()
                            . '<input type="hidden" name="study_id" value="' . (int) $study['id'] . '">'
                            . '<button type="submit" class="btn btn-primary btn-sm" aria-label="Send to librarian" title="Send to librarian">' . icon('send') . '</button></form>';
                    }
                    if ($canSendLibrary) {
                        echo '<form method="post" action="' . e(url('library/approve')) . '" class="inline-form">'
                            . \App\Helpers\Csrf::field()
                            . '<input type="hidden" name="study_id" value="' . (int) $study['id'] . '">'
                            . '<button type="submit" class="btn btn-primary btn-sm" aria-label="Submit to librarian" title="Submit to librarian">' . icon('send') . '</button></form>';
                    }
                    if ($canFinalPublish) {
                        echo '<form method="post" action="' . e(url('review/final-publish')) . '" class="inline-form">'
                            . \App\Helpers\Csrf::field()
                            . '<input type="hidden" name="study_id" value="' . (int) $study['id'] . '">'
                            . '<button type="submit" class="btn btn-primary btn-sm" aria-label="Publish" title="Publish">' . icon('check') . '</button></form>';
                    }
                    $rowActions = ob_get_clean();
                    echo action_menu($rowActions);
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr class="dash-empty-row" hidden><td><?= e($schoolWideFilterEmpty) ?></td><td></td><td></td><td></td><td></td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</div>
<?php if ($isAdminDash): ?>
    <p class="toolbar" style="margin-top:1rem">
        <a class="btn btn-primary" href="<?= e(url('review')) ?>"><?= icon('eye') ?> Open review queue</a>
    </p>
<?php endif; ?>
<?php if ($isLibDash && !empty($uploads)): ?>
    <p class="muted" style="margin-top:0.75rem"><?= count($uploads) ?> waiting at the library gate.</p>
<?php elseif ($isAdminDash && !empty($uploads)): ?>
    <p class="muted" style="margin-top:0.75rem"><?= count($uploads) ?> waiting for final publish.</p>
<?php endif; ?>
</section>
<?php require APP_PATH . '/views/partials/authors_modal.php'; ?>
<dialog id="study-quick" class="preview-modal" aria-labelledby="study-quick-heading">
    <div class="preview-modal-head">
        <h2 id="study-quick-heading">Quick view</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="study-quick-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <h3 id="study-quick-title"></h3>
    <p class="scholar-meta" id="study-quick-meta"></p>
    <p id="study-quick-abstract"></p>
    <div id="study-quick-objectives-wrap" hidden>
        <h4>Research objectives</h4>
        <p id="study-quick-objectives"></p>
    </div>
</dialog>
<?php endif; ?>

<?php if (($user['role_slug'] ?? '') === 'research_adviser'): ?>
<?php $deptCatalog = $deptCatalog ?? []; ?>
<?php $otherDeptCatalog = $otherDeptCatalog ?? []; ?>
<div class="adviser-workspace">
<?php partial('adviser_queue', ['user' => $user]); ?>
<nav class="adviser-section-nav" aria-label="Adviser workspace sections">
    <a href="#adviser-priority">Needs your review</a>
    <a href="#adviser-department">Department papers</a>
    <a href="#adviser-discover">Other departments</a>
</nav>
<section class="card card-elevated" id="adviser-department">
    <h2 class="section-title">Department papers<?= !empty($departmentLabel) ? ' — ' . e($departmentLabel) : '' ?></h2>
    <p class="muted">Every paper in your department. The whole file is available.</p>
    <?php partial('find_bar', [
        'table' => '#adviser-dept-table',
        'empty' => '#adviser-dept-table-empty',
        'papers' => $deptCatalog,
        'programs' => $programs ?? [],
        'showDepartment' => false,
    ]); ?>
    <p id="adviser-dept-table-empty" class="empty-state" hidden data-empty-text="No studies match.">No studies match.</p>
    <div class="table-scroll<?= count($deptCatalog) > 10 ? ' is-scroll' : '' ?>">
    <div class="table-wrap">
    <table class="dashboard-study-table dash-table" id="adviser-dept-table" data-sortable>
        <thead>
            <tr>
                <th>Title</th>
                <th>Student</th>
                <th>Status</th>
                <th>Year</th>
                <th data-nosort>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$deptCatalog): ?>
            <tr class="dash-empty-row"><td>No studies in your department yet.</td><td></td><td></td><td></td><td></td></tr>
        <?php else: ?>
            <?php foreach ($deptCatalog as $paper): ?>
                <tr<?= paper_find_attrs($paper) ?>>
                    <td><?= e($paper['title']) ?></td>
                    <td><?= e(trim(($paper['submitter_last'] ?? '') . ', ' . ($paper['submitter_first'] ?? ''), ', ')) ?></td>
                    <td><?= status_badge((string) ($paper['status'] ?? '')) ?></td>
                    <td><?= e($paper['school_year'] ?? '') ?></td>
                    <td class="actions-cell">
                        <?php
                        ob_start();
                        echo '<button type="button" class="btn btn-secondary btn-icon-only" data-study-quick'
                            . ' data-title="' . e($paper['title']) . '"'
                            . ' data-meta="' . e(trim(($paper['authors'] ?? '—') . ' · ' . ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? ''), ' ·')) . '"'
                            . ' data-abstract="' . e($paper['abstract'] ?? '') . '"'
                            . ' data-objectives="' . e($paper['research_objectives'] ?? '') . '"'
                            . ' aria-label="View abstract" title="View abstract">' . icon('eye') . '</button>';
                        if (can_open_full_file($user, $paper)) {
                            echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reader', ['id' => $paper['id']])) . '" aria-label="View full file" title="View full file">' . icon('book') . '</a>';
                            echo '<a class="btn btn-green btn-icon-only" href="' . e(url('file/manuscript', [
                                'study_id' => $paper['id'],
                                'version_id' => $paper['current_version_id'],
                            ])) . '" aria-label="Download full file" title="Download full file">' . icon('download') . '</a>';
                        }
                        echo action_menu(ob_get_clean());
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
</section>
<section class="card card-elevated" id="adviser-discover" style="margin-top:1.25rem">
    <h2 class="section-title">Other departments</h2>
    <p class="muted">Approved papers outside your department. Title and abstract only — full files are not available.</p>
    <?php partial('find_bar', [
        'table' => '#adviser-other-table',
        'empty' => '#adviser-other-table-empty',
        'papers' => $otherDeptCatalog,
        'programs' => array_values(array_filter($programs ?? [], static fn ($program) => (string) ($program['code'] ?? '') !== '' && (string) $program['code'] !== (string) ($user['program_code'] ?? ''))),
    ]); ?>
    <p id="adviser-other-table-empty" class="empty-state" hidden data-empty-dept="No studies in that department." data-empty-text="No studies match.">No studies in that department.</p>
    <div class="table-scroll<?= count($otherDeptCatalog) > 10 ? ' is-scroll' : '' ?>">
    <div class="table-wrap">
    <table class="dashboard-study-table dash-table" id="adviser-other-table" data-sortable>
        <thead>
            <tr>
                <th>Title</th>
                <th>Authors</th>
                <th>Department</th>
                <th>Year</th>
                <th data-nosort>Abstract</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$otherDeptCatalog): ?>
            <tr class="dash-empty-row"><td>No approved papers from other departments.</td><td></td><td></td><td></td><td></td></tr>
        <?php else: ?>
            <?php foreach ($otherDeptCatalog as $paper): ?>
                <tr<?= paper_find_attrs($paper) ?>>
                    <td><?= e($paper['title']) ?></td>
                    <td><?= author_cell($paper['authors'] ?? '', (string) $paper['title']) ?></td>
                    <td><?= e($paper['program_code'] ?? '') ?></td>
                    <td><?= e($paper['school_year'] ?? '') ?></td>
                    <td class="actions-cell">
                        <?php ob_start(); ?>
                        <button type="button" class="btn btn-secondary btn-icon-only" data-study-quick
                            data-title="<?= e($paper['title']) ?>"
                            data-meta="<?= e(trim(($paper['authors'] ?: '—') . ' · ' . ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? ''), ' ·')) ?>"
                            data-abstract="<?= e($paper['abstract'] ?? '') ?>"
                            data-objectives="<?= e($paper['research_objectives'] ?? '') ?>"
                            aria-label="View abstract" title="View abstract"><?= icon('eye') ?></button>
                        <?php if (can_open_full_file($user, $paper)): ?>
                            <a class="btn btn-secondary btn-icon-only" href="<?= e(url('reader', ['id' => $paper['id']])) ?>" aria-label="View full file" title="View full file"><?= icon('book') ?></a>
                            <a class="btn btn-green btn-icon-only" href="<?= e(url('file/manuscript', ['study_id' => $paper['id'], 'version_id' => $paper['current_version_id']])) ?>" aria-label="Download full file" title="Download full file"><?= icon('download') ?></a>
                        <?php endif; ?>
                        <?= action_menu(ob_get_clean()) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
</section>
<?php require APP_PATH . '/views/partials/authors_modal.php'; ?>
<dialog id="study-quick" class="preview-modal" aria-labelledby="study-quick-heading">
    <div class="preview-modal-head">
        <h2 id="study-quick-heading">Abstract</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="study-quick-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <h3 id="study-quick-title"></h3>
    <p class="scholar-meta" id="study-quick-meta"></p>
    <p id="study-quick-abstract"></p>
    <div id="study-quick-objectives-wrap" hidden>
        <h4>Research objectives</h4>
        <p id="study-quick-objectives"></p>
    </div>
</dialog>

</div>
<?php elseif (($user['role_slug'] ?? '') === 'dean'): ?>
<?php
$deptCatalog = $deptCatalog ?? [];
$otherDeptCatalog = $otherDeptCatalog ?? [];
$deanQueue = $uploads ?? [];
?>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<section class="card card-elevated">
    <h2 class="section-title">Department papers<?= !empty($departmentLabel) ? ' — ' . e($departmentLabel) : '' ?></h2>
    <p class="muted">All studies in your department — full manuscript available. Items at dean review can be sent to the school research coordinator.</p>
    <?php partial('find_bar', [
        'table' => '#dean-dept-table',
        'empty' => '#dean-dept-table-empty',
        'papers' => $deptCatalog,
        'programs' => $programs ?? [],
        'statusOptions' => ['' => 'Any status', 'pending' => 'Pending', 'accepted' => 'Accepted'],
        'showDepartment' => false,
    ]); ?>
    <p id="dean-dept-table-empty" class="empty-state" hidden data-empty-text="No studies match.">No studies match.</p>
    <div class="table-scroll<?= count($deptCatalog) > 10 ? ' is-scroll' : '' ?>">
    <div class="table-wrap">
    <table class="dashboard-study-table dash-table" id="dean-dept-table" data-sortable>
        <thead>
            <tr>
                <th>Title</th>
                <th>Student</th>
                <th>Status</th>
                <th>Year</th>
                <th data-nosort>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$deptCatalog): ?>
            <tr class="dash-empty-row"><td>No studies in your department yet.</td><td></td><td></td><td></td><td></td></tr>
        <?php else: ?>
            <?php foreach ($deptCatalog as $paper): ?>
                <?php
                $paperStatus = (string) ($paper['status'] ?? '');
                $paperGate = $paperStatus === 'dean_review'
                    ? 'pending'
                    : (in_array($paperStatus, ['under_review', 'library_review', 'admin_review', 'approved', 'archived'], true) ? 'accepted' : '');
                ?>
                <tr<?= paper_find_attrs($paper) ?> data-gate="<?= e($paperGate) ?>">
                    <td><?= e($paper['title']) ?></td>
                    <td><?= e(trim(($paper['submitter_last'] ?? '') . ', ' . ($paper['submitter_first'] ?? ''), ', ')) ?></td>
                    <td><?= status_badge((string) ($paper['status'] ?? '')) ?></td>
                    <td><?= e($paper['school_year'] ?? '') ?></td>
                    <td class="actions-cell">
                        <?php
                        ob_start();
                        echo '<button type="button" class="btn btn-secondary btn-icon-only" data-study-quick'
                            . ' data-title="' . e($paper['title']) . '"'
                            . ' data-meta="' . e(trim(($paper['authors'] ?? '—') . ' · ' . ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? ''), ' ·')) . '"'
                            . ' data-abstract="' . e($paper['abstract'] ?? '') . '"'
                            . ' data-objectives="' . e($paper['research_objectives'] ?? '') . '"'
                            . ' aria-label="View abstract" title="View abstract">' . icon('eye') . '</button>';
                        if (($paper['status'] ?? '') === 'dean_review') {
                            echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('review/ethics', ['id' => $paper['id']])) . '" aria-label="Open review" title="Open review">' . icon('check') . '</a>';
                        }
                        if (can_open_full_file($user, $paper)) {
                            echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reader', ['id' => $paper['id']])) . '" aria-label="View full file" title="View full file">' . icon('book') . '</a>';
                            echo '<a class="btn btn-green btn-icon-only" href="' . e(url('file/manuscript', [
                                'study_id' => $paper['id'],
                                'version_id' => $paper['current_version_id'],
                            ])) . '" aria-label="Download full file" title="Download full file">' . icon('download') . '</a>';
                        }
                        echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reports/read-log', ['study_id' => $paper['id']])) . '" aria-label="Who read this article" title="Who read this article">' . icon('list') . '</a>';
                        if (($paper['status'] ?? '') === 'dean_review') {
                            echo '<form method="post" action="' . e(url('review/dean-approve')) . '" class="inline-form">'
                                . \App\Helpers\Csrf::field()
                                . '<input type="hidden" name="study_id" value="' . (int) $paper['id'] . '">'
                                . '<button type="submit" class="btn btn-primary btn-sm" aria-label="Send to research coordinator" title="Send to research coordinator">' . icon('send') . '</button></form>';
                        }
                        echo action_menu(ob_get_clean());
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
    <?php if ($deanQueue): ?>
        <p class="muted" style="margin-top:0.75rem"><?= count($deanQueue) ?> waiting for dean approval.</p>
    <?php endif; ?>
</section>

<section class="card card-elevated" style="margin-top:1.25rem">
    <h2 class="section-title">Other departments</h2>
    <p class="muted">Approved papers outside your department. Title and abstract only — full files are not available.</p>
    <?php partial('find_bar', [
        'table' => '#dean-other-table',
        'empty' => '#dean-other-table-empty',
        'papers' => $otherDeptCatalog,
        'programs' => array_values(array_filter($programs ?? [], static fn ($program) => (string) ($program['code'] ?? '') !== '' && (string) $program['code'] !== (string) ($user['program_code'] ?? ''))),
    ]); ?>
    <p id="dean-other-table-empty" class="empty-state" hidden data-empty-dept="No studies in that department." data-empty-text="No studies match.">No studies in that department.</p>
    <div class="table-scroll<?= count($otherDeptCatalog) > 10 ? ' is-scroll' : '' ?>">
    <div class="table-wrap">
    <table class="dashboard-study-table dash-table" id="dean-other-table" data-sortable>
        <thead>
            <tr>
                <th>Title</th>
                <th>Authors</th>
                <th>Department</th>
                <th>Year</th>
                <th data-nosort>Abstract</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$otherDeptCatalog): ?>
            <tr class="dash-empty-row"><td>No approved papers from other departments.</td><td></td><td></td><td></td><td></td></tr>
        <?php else: ?>
            <?php foreach ($otherDeptCatalog as $paper): ?>
                <tr<?= paper_find_attrs($paper) ?>>
                    <td><?= e($paper['title']) ?></td>
                    <td><?= author_cell($paper['authors'] ?? '', (string) $paper['title']) ?></td>
                    <td><?= e($paper['program_code'] ?? '') ?></td>
                    <td><?= e($paper['school_year'] ?? '') ?></td>
                    <td class="actions-cell">
                        <?php ob_start(); ?>
                        <button type="button" class="btn btn-secondary btn-icon-only" data-study-quick
                            data-title="<?= e($paper['title']) ?>"
                            data-meta="<?= e(trim(($paper['authors'] ?: '—') . ' · ' . ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? ''), ' ·')) ?>"
                            data-abstract="<?= e($paper['abstract'] ?? '') ?>"
                            data-objectives="<?= e($paper['research_objectives'] ?? '') ?>"
                            aria-label="View abstract" title="View abstract"><?= icon('eye') ?></button>
                        <?php if (can_open_full_file($user, $paper)): ?>
                            <a class="btn btn-secondary btn-icon-only" href="<?= e(url('reader', ['id' => $paper['id']])) ?>" aria-label="View full file" title="View full file"><?= icon('book') ?></a>
                            <a class="btn btn-green btn-icon-only" href="<?= e(url('file/manuscript', ['study_id' => $paper['id'], 'version_id' => $paper['current_version_id']])) ?>" aria-label="Download full file" title="Download full file"><?= icon('download') ?></a>
                        <?php endif; ?>
                        <?= action_menu(ob_get_clean()) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
</section>
<?php require APP_PATH . '/views/partials/authors_modal.php'; ?>
<dialog id="study-quick" class="preview-modal" aria-labelledby="study-quick-heading">
    <div class="preview-modal-head">
        <h2 id="study-quick-heading">Abstract</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="study-quick-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <h3 id="study-quick-title"></h3>
    <p class="scholar-meta" id="study-quick-meta"></p>
    <p id="study-quick-abstract"></p>
    <div id="study-quick-objectives-wrap" hidden>
        <h4>Research objectives</h4>
        <p id="study-quick-objectives"></p>
    </div>
</dialog>

<?php elseif (($user['role_slug'] ?? '') === 'dept_research_coordinator'): ?>
<?php
$deptCatalog = $deptCatalog ?? [];
$otherDeptCatalog = $otherDeptCatalog ?? [];
$pendingUploads = $uploads ?? [];
?>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>

<section class="card card-elevated">
    <h2 class="section-title">Department papers<?= !empty($departmentLabel) ? ' — ' . e($departmentLabel) : '' ?></h2>
    <p class="muted">All studies in your department — full manuscript available. Pending items can be sent to the dean after a review note.</p>
    <?php partial('find_bar', [
        'table' => '#drc-dept-table',
        'pager' => '[data-table-pager="#drc-dept-table"]',
        'empty' => '#drc-dept-table-empty',
        'papers' => $deptCatalog,
        'programs' => $programs ?? [],
        'statusOptions' => ['' => 'Any status', 'pending' => 'Pending', 'accepted' => 'Accepted'],
        'pageSize' => 6,
        'showDepartment' => false,
    ]); ?>
    <p id="drc-dept-table-empty" class="empty-state" hidden data-empty-text="No studies match.">No studies match.</p>
    <div class="table-scroll<?= count($deptCatalog) > 10 ? ' is-scroll' : '' ?>">
    <div class="table-wrap">
    <table class="dashboard-study-table dash-table" id="drc-dept-table" data-sortable data-page-size="6">
        <thead>
            <tr>
                <th>Title</th>
                <th>Student</th>
                <th>Status</th>
                <th>Year</th>
                <th data-nosort>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$deptCatalog): ?>
            <tr class="dash-empty-row"><td>No studies in your department yet.</td><td></td><td></td><td></td><td></td></tr>
        <?php else: ?>
            <?php foreach ($deptCatalog as $paper): ?>
                <?php
                $paperStatus = (string) ($paper['status'] ?? '');
                $canSendPending = $paperStatus === 'pending'
                    && \App\Models\Study::hasLibraryReview((int) $paper['id']);
                $paperGate = $paperStatus === 'pending'
                    ? 'pending'
                    : (in_array($paperStatus, ['dean_review', 'under_review', 'library_review', 'admin_review', 'approved', 'archived'], true) ? 'accepted' : '');
                ?>
                <tr<?= paper_find_attrs($paper) ?> data-gate="<?= e($paperGate) ?>">
                    <td><?= e($paper['title']) ?></td>
                    <td><?= e(trim(($paper['submitter_last'] ?? '') . ', ' . ($paper['submitter_first'] ?? ''), ', ')) ?></td>
                    <td><?= status_badge((string) ($paper['status'] ?? '')) ?></td>
                    <td><?= e($paper['school_year'] ?? '') ?></td>
                    <td class="actions-cell">
                        <?php
                        ob_start();
                        echo '<button type="button" class="btn btn-secondary btn-icon-only" data-study-quick'
                            . ' data-title="' . e($paper['title']) . '"'
                            . ' data-meta="' . e(trim(($paper['authors'] ?? '—') . ' · ' . ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? ''), ' ·')) . '"'
                            . ' data-abstract="' . e($paper['abstract'] ?? '') . '"'
                            . ' data-objectives="' . e($paper['research_objectives'] ?? '') . '"'
                            . ' aria-label="View abstract" title="View abstract">' . icon('eye') . '</button>';
                        if (($paper['status'] ?? '') === 'pending') {
                            echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('library/review', ['id' => $paper['id']])) . '" aria-label="Open review" title="Open review">' . icon('check') . '</a>';
                        }
                        if (can_open_full_file($user, $paper)) {
                            echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reader', ['id' => $paper['id']])) . '" aria-label="View full file" title="View full file">' . icon('book') . '</a>';
                            echo '<a class="btn btn-green btn-icon-only" href="' . e(url('file/manuscript', [
                                'study_id' => $paper['id'],
                                'version_id' => $paper['current_version_id'],
                            ])) . '" aria-label="Download full file" title="Download full file">' . icon('download') . '</a>';
                        }
                        if ($canSendPending) {
                            echo '<form method="post" action="' . e(url('library/approve')) . '" class="inline-form">'
                                . \App\Helpers\Csrf::field()
                                . '<input type="hidden" name="study_id" value="' . (int) $paper['id'] . '">'
                                . '<button type="submit" class="btn btn-primary btn-sm" aria-label="Submit to dean" title="Submit to dean">' . icon('send') . '</button></form>';
                        }
                        echo action_menu(ob_get_clean());
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
    <nav class="paper-pager" data-table-pager="#drc-dept-table" hidden aria-label="Department paper pages">
        <span class="paper-pager-current" data-page-current>1</span>
        <div class="paper-pager-bar" data-page-bar></div>
    </nav>
    <?php if ($pendingUploads): ?>
        <p class="muted" style="margin-top:0.75rem"><?= count($pendingUploads) ?> waiting at the department gate.</p>
    <?php endif; ?>
</section>

<section class="card card-elevated" style="margin-top:1.25rem">
    <h2 class="section-title">Other departments</h2>
    <p class="muted">Approved papers outside your department. Title and abstract only — full files are not available.</p>
    <?php partial('find_bar', [
        'table' => '#drc-other-table',
        'empty' => '#drc-other-table-empty',
        'papers' => $otherDeptCatalog,
        'programs' => array_values(array_filter($programs ?? [], static fn ($program) => (string) ($program['code'] ?? '') !== '' && (string) $program['code'] !== (string) ($user['program_code'] ?? ''))),
    ]); ?>
    <p id="drc-other-table-empty" class="empty-state" hidden data-empty-dept="No studies in that department." data-empty-text="No studies match.">No studies in that department.</p>
    <div class="table-scroll<?= count($otherDeptCatalog) > 10 ? ' is-scroll' : '' ?>">
    <div class="table-wrap">
    <table class="dashboard-study-table dash-table" id="drc-other-table" data-sortable>
        <thead>
            <tr>
                <th>Title</th>
                <th>Authors</th>
                <th>Department</th>
                <th>Year</th>
                <th data-nosort>Abstract</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$otherDeptCatalog): ?>
            <tr class="dash-empty-row"><td>No approved papers from other departments.</td><td></td><td></td><td></td><td></td></tr>
        <?php else: ?>
            <?php foreach ($otherDeptCatalog as $paper): ?>
                <tr<?= paper_find_attrs($paper) ?>>
                    <td><?= e($paper['title']) ?></td>
                    <td><?= author_cell($paper['authors'] ?? '', (string) $paper['title']) ?></td>
                    <td><?= e($paper['program_code'] ?? '') ?></td>
                    <td><?= e($paper['school_year'] ?? '') ?></td>
                    <td class="actions-cell">
                        <?php ob_start(); ?>
                        <button type="button" class="btn btn-secondary btn-icon-only" data-study-quick
                            data-title="<?= e($paper['title']) ?>"
                            data-meta="<?= e(trim(($paper['authors'] ?: '—') . ' · ' . ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? ''), ' ·')) ?>"
                            data-abstract="<?= e($paper['abstract'] ?? '') ?>"
                            data-objectives="<?= e($paper['research_objectives'] ?? '') ?>"
                            aria-label="View abstract" title="View abstract"><?= icon('eye') ?></button>
                        <?php if (can_open_full_file($user, $paper)): ?>
                            <a class="btn btn-secondary btn-icon-only" href="<?= e(url('reader', ['id' => $paper['id']])) ?>" aria-label="View full file" title="View full file"><?= icon('book') ?></a>
                            <a class="btn btn-green btn-icon-only" href="<?= e(url('file/manuscript', ['study_id' => $paper['id'], 'version_id' => $paper['current_version_id']])) ?>" aria-label="Download full file" title="Download full file"><?= icon('download') ?></a>
                        <?php endif; ?>
                        <?= action_menu(ob_get_clean()) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    </div>
</section>
<?php require APP_PATH . '/views/partials/authors_modal.php'; ?>
<dialog id="study-quick" class="preview-modal" aria-labelledby="study-quick-heading">
    <div class="preview-modal-head">
        <h2 id="study-quick-heading">Abstract</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="study-quick-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <h3 id="study-quick-title"></h3>
    <p class="scholar-meta" id="study-quick-meta"></p>
    <p id="study-quick-abstract"></p>
    <div id="study-quick-objectives-wrap" hidden>
        <h4>Research objectives</h4>
        <p id="study-quick-objectives"></p>
    </div>
</dialog>

<?php elseif (($user['role_slug'] ?? '') === 'student'): ?>
<?php $deptCatalog = $deptCatalog ?? []; ?>

<div class="scholar-hero dash-hero">
    <h1 class="scholar-wordmark">
        <span class="wm-l">L</span><span class="wm-s">S</span><span class="wm-s2">S</span><span class="wm-t">T</span><span class="wm-i">I</span>
        <span class="wm-research">Research</span>
    </h1>
    <?php partial('find_bar', [
        'table' => '#dept-paper-cards',
        'cards' => '#dept-paper-cards',
        'pager' => '#dept-paper-pager',
        'empty' => '#dept-paper-empty',
        'papers' => $deptCatalog,
        'programs' => $programs ?? [],
        'pageSize' => 6,
        'searchId' => 'q',
        'showDepartment' => true,
    ]); ?>
    <ul id="suggestions" class="suggestions scholar-suggestions" role="listbox"></ul>
</div>
<div id="results" class="results scholar-results" aria-live="polite" data-can-rate="1" data-logged="1" data-csrf="<?= e(\App\Helpers\Csrf::token()) ?>"></div>
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

<?php if (!$deptCatalog): ?>
    <p class="empty-state">No approved papers yet.</p>
<?php else: ?>
    <p class="empty-state" id="dept-paper-empty" hidden>No studies in that department.</p>
    <div class="paper-cards student-scholar-list" id="dept-paper-cards" data-page-size="6">
        <?php foreach ($deptCatalog as $paper): ?>
            <?php $canRead = can_open_full_file($user, $paper); ?>
            <article class="paper-card<?= $canRead ? ' is-readable' : '' ?>"<?= paper_find_attrs($paper) ?>>
                <?php if ($canRead): ?><a class="paper-card-open" href="<?= e(url('reader', ['id' => $paper['id']])) ?>"><?php endif; ?>
                <h3><?= e($paper['title']) ?></h3>
                <p class="paper-card-authors"><?= author_cell($paper['authors'] ?? '', (string) $paper['title']) ?></p>
                <p class="paper-card-meta"><?= e(trim(($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? '') . ' · ' . study_type_label($paper['study_type'] ?? ''), ' ·')) ?></p>
                <?php if ($canRead): ?></a><?php endif; ?>
                <p class="publication-timestamp">Published: <?= e(publication_timestamp_label($paper['approved_at'] ?? null)) ?></p>
                <p class="scholar-snippet"><?= e(mb_strimwidth((string) ($paper['abstract'] ?? ''), 0, 320, '...')) ?></p>
                <?php if (!$canRead): ?><p class="scholar-access-note">Abstract only. Full manuscripts are available for your department.</p><?php endif; ?>
                <div class="paper-card-actions">
                <button type="button" class="student-abstract-link" data-study-quick
                    data-title="<?= e($paper['title']) ?>"
                    data-meta="<?= e(trim(($paper['authors'] ?: '—') . ' · ' . ($paper['program_code'] ?? '') . ' · ' . ($paper['school_year'] ?? ''), ' ·')) ?>"
                    data-abstract="<?= e($paper['abstract'] ?? '') ?>"
                    data-objectives="<?= e($paper['research_objectives'] ?? '') ?>"
                    aria-label="View abstract" title="View abstract">View abstract</button>
                <?php if ($canRead): ?>
                    <a href="<?= e(url('reader', ['id' => $paper['id']])) ?>">Read full text</a>
                    <a href="<?= e(url('file/manuscript', ['study_id' => $paper['id'], 'version_id' => $paper['current_version_id']])) ?>" aria-label="Download manuscript">[PDF] Download</a>
                <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <nav class="paper-pager" id="dept-paper-pager" hidden aria-label="Study pages">
        <span class="paper-pager-current" data-page-current>1</span>
        <div class="paper-pager-bar" data-page-bar></div>
    </nav>
<?php endif; ?>
<?php require APP_PATH . '/views/partials/authors_modal.php'; ?>
<dialog id="study-quick" class="preview-modal" aria-labelledby="study-quick-heading">
    <div class="preview-modal-head">
        <h2 id="study-quick-heading">Abstract</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="study-quick-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <h3 id="study-quick-title"></h3>
    <p class="scholar-meta" id="study-quick-meta"></p>
    <p id="study-quick-abstract"></p>
    <div id="study-quick-objectives-wrap" hidden>
        <h4>Research objectives</h4>
        <p id="study-quick-objectives"></p>
    </div>
</dialog>
<?php endif; ?>
