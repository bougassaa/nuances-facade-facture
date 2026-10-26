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
        $this->ensureCompanySignatureColumn();
    }

    private function ensureCompanySignatureColumn(): void
    {
        $pdo = $this->pdo();
        $exists = $pdo->query("SHOW COLUMNS FROM company LIKE 'signature_path'")->fetch();
        if ($exists === false) {
            $pdo->exec(
                'ALTER TABLE company ADD COLUMN signature_path VARCHAR(255) NULL DEFAULT NULL AFTER logo_path'
            );
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
        $pdo->exec('TRUNCATE TABLE line_designations');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
