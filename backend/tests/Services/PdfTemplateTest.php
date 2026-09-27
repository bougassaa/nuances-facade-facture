<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Services;

use Nuances\Facture\Repositories\ClientRepository;
use Nuances\Facture\Repositories\DocumentRepository;
use Nuances\Facture\Services\DocumentNumberService;
use Nuances\Facture\Services\PdfService;
use Nuances\Facture\Services\TotalsCalculator;
use Nuances\Facture\Tests\IntegrationTestCase;

final class PdfTemplateTest extends IntegrationTestCase
{
    private DocumentRepository $docs;
    private PdfService $pdf;
    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purgeBusinessData();
        $this->pdo()->exec('UPDATE company SET vat_exempt = 0, legal_quote_validity = "Valable 3 mois", payment_terms = "Paiement à réception." WHERE id = 1');
        $clients = new ClientRepository($this->pdo());
        $this->clientId = $clients->create([
            'name' => 'M. Test Client',
            'email' => 'test@example.com',
            'phone' => '0600000000',
            'address_line1' => '1 rue Test',
            'postal_code' => '84000',
            'city' => 'Avignon',
        ]);
        $this->docs = new DocumentRepository(
            $this->pdo(),
            new TotalsCalculator(),
            new DocumentNumberService($this->pdo())
        );
        $root = dirname(__DIR__, 2);
        $this->pdf = new PdfService(
            $this->pdo(),
            $root . '/templates/pdf',
            $root . '/storage/logos'
        );
    }

    public function testQuoteHtmlContainsDepositAndSignature(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'object' => 'Chantier M. TEST',
            'deposit_ttc_cents' => 29700,
            'vat_rate_bp' => 1000,
            'site_address_line1' => '56 chemin de la berche',
            'site_postal_code' => '26790',
            'site_city' => 'suze la rousse',
            'lines' => [
                [
                    'label' => 'Installation d’un échafaudage.',
                    'quantity' => 1,
                    'unit' => 'u',
                    'unit_price_ht_cents' => 90000,
                ],
            ],
        ]);
        $html = $this->pdf->renderHtml($id);
        $this->assertStringContainsString('Acompte à la signature de 297,00 €', $html);
        $this->assertStringContainsString('Bon pour travaux', $html);
        $this->assertStringContainsString('Adresse du projet', $html);
        $this->assertStringContainsString('Chantier M. TEST', $html);
        $this->assertStringContainsString('Valable 3 mois', $html);
        $this->assertStringContainsString('TVA à 10 %', $html);
        $this->assertStringNotContainsString('>TVA</th>', $html);
    }

    public function testInvoiceHtmlContainsDeductionAndRemaining(): void
    {
        $id = $this->docs->create([
            'doc_type' => 'invoice',
            'client_id' => $this->clientId,
            'object' => 'Chantier Facture',
            'deduction_label' => 'Acompte fournitures',
            'deduction_ttc_cents' => 11000,
            'vat_rate_bp' => 1000,
            'lines' => [
                [
                    'label' => 'Lavage support.',
                    'quantity' => 1,
                    'unit' => 'u',
                    'unit_price_ht_cents' => 100000,
                ],
            ],
        ]);
        $html = $this->pdf->renderHtml($id);
        $this->assertStringContainsString('Acompte fournitures', $html);
        $this->assertStringContainsString('Reste à payer', $html);
        $this->assertStringContainsString('Paiement à réception.', $html);
        $this->assertStringNotContainsString('Bon pour travaux', $html);
    }
}
