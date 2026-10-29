<?php

declare(strict_types=1);

$sql = file_get_contents(__DIR__ . '/fix_demo_role_indicators.sql');
if ($sql === false) {
    fwrite(STDERR, "Missing SQL file.\n");
    exit(1);
}

$ports = [3307, 3306, 3308];
$last = '';
$pdo = null;
foreach ($ports as $port) {
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;port=' . $port . ';dbname=lssti_research;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "Connected on port {$port}\n";
        break;
    } catch (Throwable $e) {
        $last = $e->getMessage();
    }
}
if (!$pdo) {
    fwrite(STDERR, "Could not connect: {$last}\n");
    exit(1);
}

foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || str_starts_with($stmt, '--')) {
        continue;
    }
    $pdo->exec($stmt);
}

$check = $pdo->query(
    "SELECT u.school_id, r.slug, r.name
     FROM users u INNER JOIN roles r ON r.id = u.role_id
     WHERE u.school_id IN ('STU-0001','ADV-0001','LIB-0001','DEAN-0001','SRC-0001','LIB-0002','RD-0001')
     ORDER BY u.school_id"
);
foreach ($check->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['school_id'] . ' => ' . $row['name'] . ' (' . $row['slug'] . ")\n";
}
