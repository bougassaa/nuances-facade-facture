<?php

declare(strict_types=1);

use Nuances\Facture\Database;
use Nuances\Facture\Http\Router;

$root = __DIR__;

$configFile = $root . '/config/config.local.php';
if (!is_file($configFile)) {
    $configFile = $root . '/config/config.example.php';
}
/** @var array $config */
$config = require $configFile;

require $root . '/vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

$sessionName = $config['app']['session_name'] ?? 'NFSESSID';
$secure = (bool) ($config['app']['secure_cookie'] ?? false);
$host = (string) ($config['app']['host'] ?? '');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$pdo = Database::connect($config['db']);

return [
    'config' => $config,
    'pdo' => $pdo,
    'root' => $root,
];
