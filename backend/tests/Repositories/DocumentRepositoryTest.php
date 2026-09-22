<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Repositories;

use Nuances\Facture\Domain\DocumentStatus;
use Nuances\Facture\Repositories\ClientRepository;
use Nuances\Facture\Repositories\DocumentRepository;
use Nuances\Facture\Services\DocumentNumberService;
use Nuances\Facture\Services\TotalsCalculator;
use Nuances\Facture\Tests\IntegrationTestCase;
use RuntimeException;

final class DocumentRepositoryTest extends IntegrationTestCase
{
    private DocumentRepository $docs;
    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purgeBusinessData();
        $this->pdo()->exec('UPDATE company SET vat_exempt = 0 WHERE id = 1');
        $clients = new ClientRepository($this->pdo());
        $this->clientId = $clients->create(['name' => 'Client Test Docs']);
        $this->docs = new DocumentRepository(
            $this->pdo(),
            new TotalsCalculator(),
            new DocumentNumberService($this->pdo())
        );
    }

    private function sampleLines(): array
    {
        return [
            [
                'label' => 'Enduit',
                'quantity' => 10,
                'unit' => 'm2',
                'unit_price_ht_cents' => 5000,
                'vat_rate_bp' => 1000,
            ],
            [
                'label' => 'Forfait',
                'quantity' => 1,
                'unit' => 'u',
                'unit_price_ht_cents' => 10000,
                'vat_rate_bp' => 2000,
            ],
        ];
    }

    public function testCreateComputesTotals(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'object' => 'Travaux',
            'lines' => $this->sampleLines(),
        ]);
        $doc = $this->docs->find($id);
        $this->assertNotNull($doc);
        $this->assertSame('draft', $doc['status']);
        $this->assertNull($doc['number']);
        // 10*5000=50000 + 10000 = 60000 HT ; TVA 5000+2000=7000
        $this->assertSame(60000, (int) $doc['total_ht_cents']);
        $this->assertSame(7000, (int) $doc['total_vat_cents']);
        $this->assertSame(67000, (int) $doc['total_ttc_cents']);
        $this->assertCount(2, $doc['lines']);
    }

    public function testSendAssignsNumberAndLocks(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'lines' => $this->sampleLines(),
        ]);
        $sent = $this->docs->send($id);
        $this->assertSame('sent', $sent['status']);
        $this->assertMatchesRegularExpression('/^DEV-\d{4}-\d{3}$/', (string) $sent['number']);
        $this->assertNotNull($sent['sent_at']);

        $this->expectException(RuntimeException::class);
        $this->docs->update($id, [
            'client_id' => $this->clientId,
            'object' => 'Hack',
            'lines' => $this->sampleLines(),
        ]);
    }

    public function testCannotSendWithoutLines(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'invoice',
            'client_id' => $this->clientId,
            'lines' => [],
        ]);
        $this->expectException(RuntimeException::class);
        $this->docs->send($id);
    }

    public function testAcceptAndConvertToInvoice(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'object' => 'À convertir',
            'lines' => $this->sampleLines(),
        ]);
        $this->docs->send($id);
        $this->docs->setQuoteStatus($id, DocumentStatus::Accepted);

        $invoiceId = $this->docs->convertToInvoice($id);
        $invoice = $this->docs->find($invoiceId);
        $this->assertNotNull($invoice);
        $this->assertSame('invoice', $invoice['doc_type']);
        $this->assertSame('draft', $invoice['status']);
        $this->assertNull($invoice['number']);
        $this->assertSame($id, (int) $invoice['source_document_id']);
        $this->assertSame(67000, (int) $invoice['total_ttc_cents']);
        $this->assertCount(2, $invoice['lines']);
    }

    public function testConvertRequiresAcceptedQuote(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'lines' => $this->sampleLines(),
        ]);
        $this->docs->send($id);

        $this->expectException(RuntimeException::class);
        $this->docs->convertToInvoice($id);
    }

    public function testDeleteOnlyDraft(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'lines' => $this->sampleLines(),
        ]);
        $this->docs->delete($id);
        $this->assertNull($this->docs->find($id));

        $id2 = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'lines' => $this->sampleLines(),
        ]);
        $this->docs->send($id2);
        $this->expectException(RuntimeException::class);
        $this->docs->delete($id2);
    }

    public function testInvoiceRejectsAcceptedStatus(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'invoice',
            'client_id' => $this->clientId,
            'lines' => $this->sampleLines(),
        ]);
        $this->docs->send($id);

        $this->expectException(RuntimeException::class);
        $this->docs->setQuoteStatus($id, DocumentStatus::Accepted);
    }
}
