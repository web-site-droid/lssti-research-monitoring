<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$route = isset($_GET['route']) ? (string) $_GET['route'] : '';
$route = trim($route, '/');

(new App\Router())->dispatch($route);
