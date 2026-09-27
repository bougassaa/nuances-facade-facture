<?php

declare(strict_types=1);

use Nuances\Facture\Auth\SessionConfigurator;
use Nuances\Facture\Database;

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

SessionConfigurator::start($root, $sessionName, $secure);

$pdo = Database::connect($config['db']);

return [
    'config' => $config,
    'pdo' => $pdo,
    'root' => $root,
];
