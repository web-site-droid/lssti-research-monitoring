<div class="page-header">
    <h1>Reports</h1>
    <p class="lead">See how many studies each department sent, and who sent them.</p>
</div>
<form class="review-tools" id="report-tools">
    <label class="review-label" for="report-program">Department</label>
    <select id="report-program">
        <option value="">All departments</option>
        <?php foreach ($departments as $row): ?>
            <option value="<?= e($row['code']) ?>"><?= e($row['code']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<section class="card card-elevated paper-group is-wide">
    <h2 class="section-title section-title-plain">By department</h2>
    <div class="table-scroll<?= count($departments) > 10 ? ' is-scroll' : '' ?>">
        <div class="table-wrap dash-board">
            <table class="dash-table report-table report-dept" id="report-dept">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Submitted</th>
                        <th>Approved</th>
                        <th>In review</th>
                        <th>Revision</th>
                        <th>Students</th>
                        <th>Advisers</th>
                        <th>Who submitted</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$departments): ?>
                    <tr class="dash-empty-row"><td>No departments.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                <?php endif; ?>
                <?php foreach ($departments as $row): ?>
                    <tr data-program="<?= e($row['code']) ?>">
                        <td><?= e($row['code']) ?></td>
                        <td><?= (int) $row['submitted'] ?></td>
                        <td><?= (int) $row['approved'] ?></td>
                        <td><?= (int) $row['in_review'] ?></td>
                        <td><?= (int) $row['revision'] ?></td>
                        <td><?= (int) $row['students'] ?></td>
                        <td><?= (int) $row['advisers'] ?></td>
                        <td class="actions-cell">
                            <button type="button" class="btn btn-secondary btn-icon-only" data-submitters-open
                                data-program="<?= e($row['code']) ?>"
                                data-rows="<?= e(json_encode($row['people'], JSON_UNESCAPED_UNICODE)) ?>"
                                aria-label="Who submitted" title="Who submitted"><?= icon('more') ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($departments): ?>
                    <tr class="report-empty-row" hidden><td>No data for this department.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<dialog id="submitters-quick" class="preview-modal" aria-labelledby="submitters-quick-heading">
    <div class="preview-modal-head">
        <h2 id="submitters-quick-heading">Who submitted</h2>
        <button type="button" class="btn btn-secondary btn-icon-only" id="submitters-quick-close" aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div class="table-wrap dash-board">
        <table class="dash-table report-table report-people">
            <thead>
                <tr>
                    <th>ID no.</th>
                    <th>Name</th>
                    <th>Submitted</th>
                    <th>Approved</th>
                    <th>Last update</th>
                </tr>
            </thead>
            <tbody id="submitters-quick-body"></tbody>
        </table>
    </div>
</dialog>
