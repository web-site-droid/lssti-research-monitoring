<?php

declare(strict_types=1);

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', dirname(__DIR__) . '/config');
}
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__) . '/app');
}

require_once CONFIG_PATH . '/config.php';
require_once CONFIG_PATH . '/supabase.php';
require_once CONFIG_PATH . '/database.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    if (count($parts) === 1) {
        $rootFile = APP_PATH . '/' . $parts[0] . '.php';
        if (is_file($rootFile)) {
            require_once $rootFile;
        }
        return;
    }
    $dir = strtolower($parts[0]);
    $file = APP_PATH . '/' . $dir . '/' . implode('/', array_slice($parts, 1)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once APP_PATH . '/helpers/functions.php';

App\Helpers\Session::start();
App\Helpers\StorageFiles::ensureSchema();
App\Helpers\IpAllowlist::enforce();
