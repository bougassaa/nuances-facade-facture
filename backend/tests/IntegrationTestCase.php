<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests;

use Nuances\Facture\Database;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected static ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$pdo === null) {
            self::$pdo = self::connectOrSkip();
        }
    }

    protected function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connectOrSkip();
        }
        return self::$pdo;
    }

    private static function connectOrSkip(): PDO
    {
        $file = dirname(__DIR__) . '/config/config.local.php';
        if (!is_file($file)) {
            self::markTestSkipped('config.local.php manquant');
        }
        /** @var array $config */
        $config = require $file;
        try {
            return Database::connect($config['db']);
        } catch (\Throwable $e) {
            self::markTestSkipped('MySQL indisponible: ' . $e->getMessage());
        }
    }

    protected function purgeBusinessData(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE document_lines');
        $pdo->exec('TRUNCATE TABLE documents');
        $pdo->exec('TRUNCATE TABLE counters');
        $pdo->exec('TRUNCATE TABLE clients');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
