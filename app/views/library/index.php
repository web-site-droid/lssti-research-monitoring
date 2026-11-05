<?php
$programs = [];
foreach ($studies as $study) {
    $programs[$study['program_code']] = $study['program_code'];
}
ksort($programs);
?>
<?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<?php partial('find_bar', [
    'table' => '#upload-table',
    'cards' => '#library-read-cards',
    'papers' => $studies,
    'programs' => $programs,
    'statusOptions' => [
        '' => 'Any status',
        'pending' => 'With dept coordinator',
        'library_review' => 'With librarian',
        'admin_review' => 'With librarian',
        'approved' => 'Approved',
    ],
    'statusAttr' => 'data-status',
]); ?>
<p class="muted">Click a card to read the whole file and play it aloud.</p>
<div class="paper-cards" id="library-read-cards">
    <?php foreach ($studies as $paper): ?>
        <?php if (can_open_full_file($user ?? \App\Helpers\Auth::user(), $paper)) partial('paper_read_card', ['paper' => $paper]); ?>
    <?php endforeach; ?>
</div>
<div class="table-scroll<?= count($studies) > 10 ? ' is-scroll' : '' ?>">
<div class="table-wrap">
<table class="dash-table" id="upload-table">
    <thead>
        <tr>
            <th>Title</th>
            <th>Student</th>
            <th>Program</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$studies): ?>
        <tr class="dash-empty-row"><td>No uploads yet.</td><td></td><td></td><td></td><td></td></tr>
    <?php endif; ?>
    <?php foreach ($studies as $s): ?>
        <?php
        $canSend = in_array($s['status'], ['pending', 'library_review'], true)
            && \App\Models\Study::hasLibraryReview((int) $s['id']);
        $sendLabel = ($s['status'] ?? '') === 'library_review' ? 'Submit to librarian' : 'Submit to dean';
        $statusLabel = match ($s['status']) {
            'pending' => 'With dept coordinator',
            'library_review' => 'With librarian',
            'admin_review' => 'With librarian',
            'approved' => 'Approved',
            default => ucwords(str_replace('_', ' ', $s['status'])),
        };
        ?>
        <tr<?= paper_find_attrs($s) ?> data-status="<?= e($s['status']) ?>">
            <td><?= e($s['short_title']) ?></td>
            <td><?= e($s['last_name'] . ', ' . $s['first_name']) ?></td>
            <td><?= e($s['program_code']) ?></td>
            <td><?= status_badge((string) $s['status'], $statusLabel) ?></td>
            <td class="actions actions-cell">
                <?php
                ob_start();
                if ($s['status'] === 'approved') {
                    echo '<button type="button" class="btn btn-secondary btn-icon-only" disabled aria-label="Review" title="Review">' . icon('eye') . '</button>';
                } else {
                    echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('library/review', ['id' => $s['id']])) . '" aria-label="Review" title="Review">' . icon('eye') . '</a>';
                }
                if (can_open_full_file($user ?? \App\Helpers\Auth::user(), $s)) {
                    echo '<a class="btn btn-secondary btn-icon-only" href="' . e(url('reader', ['id' => $s['id']])) . '" aria-label="View full file" title="View full file">' . icon('book') . '</a>';
                    echo '<a class="btn btn-green btn-icon-only" href="' . e(url('file/manuscript', ['study_id' => $s['id'], 'version_id' => $s['current_version_id']])) . '" aria-label="Download" title="Download">' . icon('download') . '</a>';
                }
                if ($canSend) {
                    echo '<form method="post" action="' . e(url('library/approve')) . '" class="inline-form">'
                        . \App\Helpers\Csrf::field()
                        . '<input type="hidden" name="study_id" value="' . (int) $s['id'] . '">'
                        . '<button type="submit" class="btn btn-primary btn-sm" aria-label="' . e($sendLabel) . '" title="' . e($sendLabel) . '">' . icon('send') . '</button></form>';
                }
                echo action_menu(ob_get_clean());
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
        <tr class="dash-empty-row" id="upload-empty" hidden><td>No uploads match.</td><td></td><td></td><td></td><td></td></tr>
    </tbody>
</table>
</div>
</div>
