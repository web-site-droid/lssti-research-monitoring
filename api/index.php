<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
$route = isset($_GET['route']) ? (string) $_GET['route'] : '';
if ($route === '' && preg_match('#/login\\.php$#', $path) === 1) {
    $route = 'login';
}
$route = trim($route, '/');

(new App\Router())->dispatch($route);
