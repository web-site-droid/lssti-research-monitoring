<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $user = trim((string) DB_USER);
    $pass = (string) DB_PASS;
    $name = trim((string) DB_NAME);
    $charset = trim((string) DB_CHARSET);
    $timeout = VERCEL_DEPLOY ? 4 : 1;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => $timeout,
    ];

    $hosts = array_values(array_filter([trim((string) DB_HOST)]));
    $ports = array_values(array_filter([defined('DB_PORT') ? (int) DB_PORT : 0]));
    if (VERCEL_DEPLOY) {
        if ($hosts === []) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>Database not available</h1>';
            echo '<p>Set DB_HOST, DB_NAME, DB_USER, and DB_PASS in the Vercel project environment variables.</p>';
            echo '<p>Use the Hostinger remote MySQL host from hPanel. localhost will not work on Vercel.</p>';
            exit;
        }
        if ($ports === []) {
            $ports = [3306];
        }
    } else {
        $hosts = array_values(array_unique(array_merge($hosts, ['127.0.0.1'])));
        $ports = array_values(array_unique(array_filter(array_merge([3307], $ports, [3306]))));
    }

    $hint = rtrim((string) (VERCEL_DEPLOY ? sys_get_temp_dir() : STORAGE_PATH), '/\\') . DIRECTORY_SEPARATOR . '.db-ok';
    $pairs = [];
    if (is_file($hint)) {
        $saved = explode('|', trim((string) file_get_contents($hint)), 2);
        if (count($saved) === 2 && $saved[0] !== '' && ctype_digit($saved[1])) {
            $pairs[] = [$saved[0], (int) $saved[1]];
        }
    }
    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            $pairs[] = [$host, $port];
        }
    }
    $seen = [];
    $last = null;
    foreach ($pairs as [$host, $port]) {
        $key = $host . ':' . $port;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            $hintDir = dirname($hint);
            if (!is_dir($hintDir)) {
                @mkdir($hintDir, 0775, true);
            }
            @file_put_contents($hint, $host . '|' . $port);
            return $pdo;
        } catch (PDOException $e) {
            $last = $e;
        }
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Database not available</h1>';
    echo '<p>Check the MySQL host, database name, username, and password.</p>';
    echo '<p>On this computer, start MySQL in XAMPP, then refresh.</p>';
    echo '<p>On Vercel, set DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASS in the Vercel project environment variables. Use the Hostinger remote MySQL host from hPanel, not localhost.</p>';
    echo '<p>On Hostinger, confirm the database name, user, and password in hPanel match config.php, then import database/u471965942_researchdb.sql in phpMyAdmin.</p>';
    echo '<p><small>' . htmlspecialchars($last ? $last->getMessage() : 'Unknown connection error', ENT_QUOTES, 'UTF-8') . '</small></p>';
    exit;
}
