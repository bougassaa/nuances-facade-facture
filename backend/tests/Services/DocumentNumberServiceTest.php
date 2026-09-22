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

        $this->assertSame('DEV-2099-001', $n1);
        $this->assertSame('DEV-2099-002', $n2);
        $this->assertSame('DEV-2099-003', $n3);
    }

    public function testInvoiceUsesFacPrefix(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('DELETE FROM counters WHERE doc_type = "invoice" AND year = 2098');
        $svc = new DocumentNumberService($pdo);

        $pdo->beginTransaction();
        $n = $svc->next(DocumentType::Invoice, 2098);
        $pdo->commit();

        $this->assertSame('FAC-2098-001', $n);
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

        $this->assertSame('DEV-2097-001', $q);
        $this->assertSame('FAC-2097-001', $i);
    }
}
