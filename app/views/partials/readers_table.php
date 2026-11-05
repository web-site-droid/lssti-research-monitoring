<?php
/** @var list<array<string,mixed>> $readers */
$readers = $readers ?? [];
$emptyText = $emptyText ?? 'No one has opened this article yet.';
$timeLabel = $timeLabel ?? 'Read';
$timeKey = $timeKey ?? 'opened_at';
?>
<?php if ($readers === []): ?>
    <p class="empty-state"><?= e($emptyText) ?></p>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table" data-sortable>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ID no.</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th><?= e($timeLabel) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($readers as $row): ?>
                <?php $programCode = trim((string) ($row['program_code'] ?? '')); ?>
                <tr>
                    <td><?= e(($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '')) ?></td>
                    <td><?= e((string) ($row['school_id'] ?? '')) ?></td>
                    <td><?= e(\App\Helpers\Role::label('', (string) ($row['role_name'] ?? ''))) ?></td>
                    <td><?= $programCode !== '' ? e($programCode) : 'All departments' ?></td>
                    <td><?= e((string) ($row[$timeKey] ?? $row['opened_at'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
