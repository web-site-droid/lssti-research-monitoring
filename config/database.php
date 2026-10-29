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
    $hosts = array_values(array_filter([trim((string) DB_HOST)]));
    if (!VERCEL_DEPLOY) {
        $hosts = array_values(array_unique(array_merge($hosts, ['127.0.0.1', 'localhost'])));
    }
    $ports = array_values(array_unique(array_filter([
        defined('DB_PORT') ? (int) DB_PORT : 0,
        VERCEL_DEPLOY ? 0 : 3307,
        3306,
    ])));
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (VERCEL_DEPLOY && $hosts === []) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Database not available</h1>';
        echo '<p>Set DB_HOST, DB_NAME, DB_USER, and DB_PASS in the Vercel project environment variables.</p>';
        echo '<p>Use the Hostinger remote MySQL host from hPanel. localhost will not work on Vercel.</p>';
        exit;
    }

    $last = null;
    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
            try {
                $pdo = new PDO($dsn, $user, $pass, $options);
                return $pdo;
            } catch (PDOException $e) {
                $last = $e;
            }
        }
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Database not available</h1>';
    echo '<p>Check the MySQL host, database name, username, and password.</p>';
    echo '<p>On this computer, start MySQL in XAMPP, then refresh.</p>';
    echo '<p>On Vercel, set DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASS in the project environment variables. Use the Hostinger remote MySQL host from hPanel, not localhost.</p>';
    echo '<p>On Hostinger, confirm the database name, user, and password in hPanel match config.php, then import database/u471965942_researchdb.sql in phpMyAdmin.</p>';
    echo '<p><small>' . htmlspecialchars($last ? $last->getMessage() : 'Unknown connection error', ENT_QUOTES, 'UTF-8') . '</small></p>';
    exit;
}
