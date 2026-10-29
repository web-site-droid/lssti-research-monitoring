<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($pageTitle ?? 'Report') ?></title>
    <link rel="icon" type="image/png" href="<?= e(rtrim(APP_URL_PATH, '/')) ?>/assets/images/logo.png">
    <link rel="stylesheet" href="<?= e(rtrim(APP_URL_PATH, '/')) ?>/assets/css/print.css">
</head>
<body class="print-report">
<header class="print-header">
    <img src="<?= e(rtrim(APP_URL_PATH, '/')) ?>/assets/images/logo.png" alt="Department of Computer Science, LSSTI" width="64" height="64">
    <div>
        <h1>Lanao School of Science and Technology, Inc.</h1>
        <p><?= e(APP_NAME) ?> — <?= e(ucfirst($report ?? 'summary')) ?> report</p>
        <p>Generated: <?= e(date('Y-m-d H:i')) ?></p>
    </div>
</header>
<section>
    <h2>Studies by program, year, status</h2>
    <table>
        <thead><tr><th>Program</th><th>School year</th><th>Status</th><th>Count</th></tr></thead>
        <tbody>
        <?php foreach ($studiesGrouped as $row): ?>
            <tr><td><?= e($row['code']) ?></td><td><?= e($row['school_year']) ?></td><td><?= e($row['status']) ?></td><td><?= (int) $row['c'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<section>
    <h2>Most read studies</h2>
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>Reads</th></tr></thead>
        <tbody>
        <?php foreach ($mostRead as $row): ?>
            <tr><td><?= (int) $row['id'] ?></td><td><?= e($row['title']) ?></td><td><?= (int) $row['c'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<section>
    <h2>Pending reviews / revisions</h2>
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>Program</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($pending as $row): ?>
            <tr><td><?= (int) $row['id'] ?></td><td><?= e($row['short_title']) ?></td><td><?= e($row['code']) ?></td><td><?= e($row['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<section>
    <h2>Plagiarism above 10%</h2>
    <table>
        <thead><tr><th>ID</th><th>Title</th><th>%</th></tr></thead>
        <tbody>
        <?php foreach ($plagiarismHigh as $row): ?>
            <tr><td><?= (int) $row['id'] ?></td><td><?= e($row['title']) ?></td><td><?= e((string) $row['similarity_percentage']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<script>window.print();</script>
</body>
</html>
