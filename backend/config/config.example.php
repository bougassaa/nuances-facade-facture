<?php

declare(strict_types=1);

/**
 * Copier vers config.local.php et renseigner les valeurs.
 * Ne jamais committer config.local.php.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'nuances_facture',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        // Hôte public (cookie de session). En local: 127.0.0.1
        'host' => '127.0.0.1',
        'session_name' => 'NFSESSID',
        'secure_cookie' => false,
        'debug' => true,
    ],
    'paths' => [
        'logos' => __DIR__ . '/../storage/logos',
    ],
];
