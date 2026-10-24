<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Services;

use Nuances\Facture\Domain\DocumentType;
use Nuances\Facture\Services\DocumentNumberService;
use Nuances\Facture\Tests\IntegrationTestCase;

final class DocumentNumberServiceTest extends IntegrationTestCase
{
    public function testFormatPrefixes(): void
    {
        $this->assertSame('DEV', DocumentType::Quote->numberPrefix());
        $this->assertSame('FAC', DocumentType::Invoice->numberPrefix());
    }

    public function testNextIncrementsWithoutGaps(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM counters WHERE doc_type = "quote" AND year = 2099');
        $svc = new DocumentNumberService($pdo);

        $pdo->beginTransaction();
        $n1 = $svc->next(DocumentType::Quote, 2099);
        $n2 = $svc->next(DocumentType::Quote, 2099);
        $n3 = $svc->next(DocumentType::Quote, 2099);
        $pdo->commit();

        $this->assertSame('DEV-2099-0001', $n1);
        $this->assertSame('DEV-2099-0002', $n2);
        $this->assertSame('DEV-2099-0003', $n3);
    }

    public function testInvoiceUsesFacPrefix(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM counters WHERE doc_type = "invoice" AND year = 2098');
        $svc = new DocumentNumberService($pdo);

        $pdo->beginTransaction();
        $n = $svc->next(DocumentType::Invoice, 2098);
        $pdo->commit();

        $this->assertSame('FAC-2098-0001', $n);
    }

    public function testIndependentCountersPerType(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM counters WHERE year = 2097');
        $svc = new DocumentNumberService($pdo);

        $pdo->beginTransaction();
        $q = $svc->next(DocumentType::Quote, 2097);
        $i = $svc->next(DocumentType::Invoice, 2097);
        $pdo->commit();

        $this->assertSame('DEV-2097-0001', $q);
        $this->assertSame('FAC-2097-0001', $i);
    }

    public function testSeedIfEmptyThenNextContinues(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM counters WHERE doc_type = "quote" AND year = 2096');
        $pdo->exec('DELETE FROM documents WHERE number LIKE "DEV-2096-%"');
        $svc = new DocumentNumberService($pdo);

        $svc->setLastNumber(DocumentType::Quote, 2096, 120);

        $pdo->beginTransaction();
        $n = $svc->next(DocumentType::Quote, 2096);
        $pdo->commit();

        $this->assertSame('DEV-2096-0121', $n);
    }

    public function testSetLastNumberOverridesExistingCounter(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM counters WHERE doc_type = "invoice" AND year = 2095');
        $pdo->exec('DELETE FROM documents WHERE number LIKE "FAC-2095-%"');
        $clientId = $this->insertClient('Client numérotation');
        try {
            $pdo->prepare(
                'INSERT INTO documents (doc_type, status, number, client_id) VALUES ("invoice", "sent", "FAC-2095-0001", :client)'
            )->execute(['client' => $clientId]);

            $svc = new DocumentNumberService($pdo);
            $svc->setLastNumber(DocumentType::Invoice, 2095, 500);

            $pdo->beginTransaction();
            $n = $svc->next(DocumentType::Invoice, 2095);
            $pdo->commit();
            $this->assertSame('FAC-2095-0501', $n);
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('DELETE FROM documents WHERE number LIKE "FAC-2095-%"');
            $pdo->prepare('DELETE FROM clients WHERE id = :id')->execute(['id' => $clientId]);
            $pdo->exec('DELETE FROM counters WHERE doc_type = "invoice" AND year = 2095');
        }
    }

    public function testSetLastNumberCanResetToZero(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM counters WHERE doc_type = "quote" AND year = 2094');
        $svc = new DocumentNumberService($pdo);

        $svc->setLastNumber(DocumentType::Quote, 2094, 80);
        $svc->setLastNumber(DocumentType::Quote, 2094, 0);

        $pdo->beginTransaction();
        $n = $svc->next(DocumentType::Quote, 2094);
        $pdo->commit();
        $this->assertSame('DEV-2094-0001', $n);
    }

    private function insertClient(string $name): int
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('INSERT INTO clients (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        return (int) $pdo->lastInsertId();
    }
}
