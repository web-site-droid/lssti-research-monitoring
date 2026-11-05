<?php
$pageTitle = 'Study';
$studyMonths = [];
foreach ($studies as $study) {
    $stamp = strtotime((string) ($study['submitted_at'] ?: $study['created_at'])) ?: time();
    $month = date('Y-m', $stamp);
    $day = date('Y-m-d', $stamp);
    if (!isset($studyMonths[$month])) {
        $studyMonths[$month] = ['label' => date('F Y', $stamp), 'days' => []];
    }
    $studyMonths[$month]['days'][$day][] = $study;
}
?>
<div class="page-header">
    <h1>Study</h1>
    <p class="lead">Studies are stored in a month folder, then in a folder for each day.</p>
</div>
<?php if ($success = flash('success')): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if ($error = flash('error')): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?php partial('find_bar', [
    'table' => '#folder-table',
    'papers' => $studies,
    'programs' => $programs,
    'statusOptions' => [
        '' => 'Any status',
        'submitted' => 'Submitted',
        'pending' => 'Pending',
        'dean_review' => 'Dean review',
        'under_review' => 'Under review',
        'library_review' => 'Librarian review',
        'admin_review' => 'Librarian review',
        'approved' => 'Approved',
        'revision_requested' => 'Revision requested',
        'archived' => 'Archived',
    ],
    'statusAttr' => 'data-status',
    'mode' => 'delegate',
]); ?>
<section class="study-folders" id="study-folders">
    <div class="study-folders-bar">
        <button type="button" class="folder-back" id="folder-back" hidden aria-label="Back"><?= icon('arrow-left') ?></button>
        <p class="folder-crumb"><span>Studies</span><span id="folder-month" hidden></span><span id="folder-crumb" hidden></span></p>
    </div>
    <div class="study-folders-body">
        <aside class="study-folders-side" aria-label="Folders">
            <?php if (!$studyMonths): ?>
                <p class="folder-empty">No submitted studies yet.</p>
            <?php endif; ?>
            <?php foreach ($studyMonths as $month => $group): ?>
                <?php $dayCount = count($group['days']); ?>
                <button type="button" class="folder-day" data-open-month="<?= e($month) ?>">
                    <span class="folder-mark" aria-hidden="true"></span>
                    <span class="folder-day-text">
                        <strong><?= e($group['label']) ?></strong>
                        <span><?= $dayCount === 1 ? '1 date' : $dayCount . ' dates' ?></span>
                    </span>
                </button>
                <?php foreach ($group['days'] as $day => $items): ?>
                    <?php $latest = strtotime((string) ($items[0]['submitted_at'] ?: $items[0]['created_at'])); ?>
                    <button type="button" class="folder-day folder-sub" data-open-folder="<?= e($day) ?>" data-month="<?= e($month) ?>">
                        <span class="folder-mark" aria-hidden="true"></span>
                        <span class="folder-day-text">
                            <strong><?= e(date('M j, Y', $latest)) ?></strong>
                            <span><?= e(date('g:i A', $latest)) ?> · <?= count($items) === 1 ? '1 study' : count($items) . ' studies' ?></span>
                        </span>
                    </button>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </aside>
        <div class="study-folders-main">
            <div class="table-wrap">
                <table class="dash-table folder-table" id="folder-table">
                    <colgroup>
                        <col class="col-title">
                        <col class="col-authors">
                        <col class="col-dept">
                        <col class="col-status">
                        <col class="col-action">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Authors</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($studyMonths as $group): ?>
                        <?php foreach ($group['days'] as $day => $items): ?>
                        <?php foreach ($items as $study): ?>
                            <tr class="folder-file" data-day="<?= e($day) ?>"<?= paper_find_attrs($study) ?> data-status="<?= e($study['status']) ?>" hidden>
                                <td><?= e($study['short_title'] ?: $study['title']) ?></td>
                                <td><?= e($study['authors'] ?: '—') ?></td>
                                <td><?= e($study['program_code']) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', $study['status']))) ?></td>
                                <td class="actions-cell">
                                    <?php ob_start(); ?>
                                    <button type="button" class="btn btn-secondary btn-icon-only" data-study-quick
                                        data-title="<?= e($study['title']) ?>"
                                        data-meta="<?= e(trim(($study['authors'] ?: '—') . ' · ' . ($study['program_code'] ?? '') . ' · ' . ($study['school_year'] ?? ''), ' ·')) ?>"
                                        data-abstract="<?= e($study['abstract'] ?? '') ?>"
                                        data-objectives="<?= e($study['research_objectives'] ?? '') ?>"
                                        aria-label="View" title="View"><?= icon('eye') ?></button>
                                    <?php if (can_open_full_file($user ?? \App\Helpers\Auth::user(), $study)): ?>
                                        <a class="btn btn-secondary btn-icon-only" href="<?= e(url('reader', ['id' => $study['id']])) ?>" aria-label="View full file" title="View full file"><?= icon('book') ?></a>
                                        <a class="btn btn-green btn-icon-only" href="<?= e(url('file/manuscript', ['study_id' => $study['id'], 'version_id' => $study['current_version_id']])) ?>" aria-label="Download" title="Download"><?= icon('download') ?></a>
                                    <?php endif; ?>
                                    <?php if (\App\Models\Study::canDeletePublished(\App\Helpers\Auth::user(), $study)): ?>
                                        <form method="post" action="<?= e(url('studies/delete-published')) ?>" data-confirm="<?= e('Permanently delete the published study “' . $study['title'] . '”? Its manuscript versions, reviews and related study records will be removed. This cannot be undone.') ?>">
                                            <?= \App\Helpers\Csrf::field() ?>
                                            <input type="hidden" name="study_id" value="<?= (int) $study['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-icon-only" aria-label="Delete published study" title="Delete published study"><?= icon('trash') ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?= action_menu(ob_get_clean()) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                        <tr id="folder-none" hidden><td colspan="5">Choose a folder to see its studies.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <p class="study-folders-foot" id="folder-foot"><?= count($studyMonths) === 1 ? '1 item' : count($studyMonths) . ' items' ?></p>
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
<script>
(function () {
  var root = document.getElementById('study-folders');
  if (!root) return;
  var back = document.getElementById('folder-back');
  var monthCrumb = document.getElementById('folder-month');
  var crumb = document.getElementById('folder-crumb');
  var foot = document.getElementById('folder-foot');
  var none = document.getElementById('folder-none');
  var findBar = document.querySelector('[data-find-table="#folder-table"]');
  document.addEventListener('find-change', paint);
  function findValue(name) {
    var el = findBar ? findBar.querySelector('[data-find-' + name + ']') : null;
    return el ? el.value : '';
  }
  var openMonth = '';
  var openDay = '';
  var expanded = {};
  root.querySelectorAll('[data-open-month]').forEach(function (item) {
    expanded[item.getAttribute('data-open-month')] = true;
  });

  function nameOf(selector) {
    var button = root.querySelector(selector + ' strong');
    return button ? button.textContent : '';
  }
  function rowMatches(row, day) {
    var program = findValue('dept');
    var type = findValue('type');
    var year = findValue('year');
    var status = findValue('status');
    var from = findValue('from');
    var to = findValue('to');
    var queryEl = findBar ? findBar.querySelector('[data-find-q]') : null;
    var query = queryEl ? queryEl.value.trim().toLowerCase() : '';
    var month = row.getAttribute('data-month') || '';
    var matchProgram = !program || row.getAttribute('data-program') === program;
    var matchType = !type || row.getAttribute('data-type') === type;
    var matchYear = !year || row.getAttribute('data-year') === year;
    var matchStatus = !status || row.getAttribute('data-status') === status;
    var matchMonth = (!from && !to) || (month && (!from || month >= from) && (!to || month <= to));
    var matchText = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
    return matchProgram && matchType && matchYear && matchStatus && matchMonth && matchText && (day === '' || row.getAttribute('data-day') === day);
  }
  function paint() {
    var items = 0;
    var visibleDays = 0;
    var visibleMonths = 0;
    root.querySelectorAll('.folder-file').forEach(function (row) {
      var show = openDay !== '' && rowMatches(row, openDay);
      row.hidden = !show;
      if (show) items += 1;
    });
    root.querySelectorAll('[data-open-month]').forEach(function (item) {
      var month = item.getAttribute('data-open-month');
      var count = 0;
      root.querySelectorAll('[data-month="' + month + '"]').forEach(function (dayButton) {
        var day = dayButton.getAttribute('data-open-folder');
        var studies = 0;
        root.querySelectorAll('.folder-file[data-day="' + day + '"]').forEach(function (row) {
          if (rowMatches(row, day)) studies += 1;
        });
        if (studies > 0) count += 1;
      });
      item.hidden = count === 0;
      item.classList.toggle('is-on', openMonth === month && openDay === '');
      if (!item.hidden) visibleMonths += 1;
    });
    root.querySelectorAll('[data-open-folder]').forEach(function (item) {
      var day = item.getAttribute('data-open-folder');
      var studies = 0;
      root.querySelectorAll('.folder-file[data-day="' + day + '"]').forEach(function (row) {
        if (rowMatches(row, day)) studies += 1;
      });
      var show = studies > 0 && expanded[item.getAttribute('data-month')];
      item.hidden = !show;
      item.classList.toggle('is-on', day === openDay);
      if (show) visibleDays += 1;
    });
    if (back) back.hidden = openMonth === '';
    if (monthCrumb) {
      monthCrumb.hidden = openMonth === '';
      monthCrumb.textContent = openMonth ? nameOf('[data-open-month="' + openMonth + '"]') : '';
    }
    if (crumb) {
      crumb.hidden = openDay === '';
      crumb.textContent = openDay ? nameOf('[data-open-folder="' + openDay + '"]') : '';
    }
    if (none) {
      none.hidden = openDay !== '' && items !== 0;
      none.querySelector('td').textContent = openDay === '' ? 'Open a date folder to see its studies.' : 'No studies in this department.';
    }
    if (foot) {
      var count = openDay !== '' ? items : visibleMonths + visibleDays;
      foot.textContent = count === 1 ? '1 item' : count + ' items';
    }
  }
  root.addEventListener('click', function (event) {
    var month = event.target.closest('[data-open-month]');
    var day = event.target.closest('[data-open-folder]');
    if (month) {
      var key = month.getAttribute('data-open-month');
      expanded[key] = !expanded[key];
      if (!expanded[key]) {
        var openButton = openDay ? root.querySelector('[data-open-folder="' + openDay + '"]') : null;
        if (!openButton || openButton.getAttribute('data-month') === key) openDay = '';
        if (openMonth === key) openMonth = '';
      } else {
        openMonth = key;
        openDay = '';
      }
      paint();
    } else if (day) {
      openMonth = day.getAttribute('data-month');
      openDay = day.getAttribute('data-open-folder');
      paint();
    }
  });
  if (back) back.addEventListener('click', function () {
    if (openDay) openDay = '';
    else openMonth = '';
    paint();
  });
  paint();
})();
</script>
