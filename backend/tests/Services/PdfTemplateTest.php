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
        $this->pdo()->exec('UPDATE company SET vat_exempt = 0, quote_validity_months = 2, quote_deposit_percent = 30, payment_terms = "Paiement à réception.", signature_path = NULL WHERE id = 1');
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
            'deposit_percent' => 30,
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
        // 900,00 € HT + 10 % = 990,00 € TTC ; 30 % = 297,00 €
        $this->assertStringContainsString('Acompte à la signature de 30 %, soit 297,00 €.', $html);
        $this->assertStringContainsString('Bon pour travaux', $html);
        $this->assertStringContainsString('Adresse du projet', $html);
        $this->assertMatchesRegularExpression('/class="project-address"[^>]*>\s*56 chemin de la berche/u', $html);
        $this->assertMatchesRegularExpression('/class="object"[^>]*>\s*Chantier M. TEST\s*</u', $html);
        $this->assertStringContainsString('Valable 2 mois', $html);
        $this->assertStringContainsString('TVA à 10 %', $html);
        $this->assertStringContainsString('>TVA</th>', $html);
        $this->assertMatchesRegularExpression('/<td class="vat">\s*10 %\s*<\/td>/u', $html);
        $this->assertStringContainsString('Le client', $html);
        $this->assertStringNotContainsString('class="signature-img"', $html);
    }

    public function testQuoteHtmlHidesDepositAndValidityWhenZero(): void
    {
        $this->pdo()->exec('UPDATE company SET quote_validity_months = 0 WHERE id = 1');
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'deposit_percent' => 0,
            'lines' => [
                ['label' => 'Enduit', 'quantity' => 1, 'unit' => 'u', 'unit_price_ht_cents' => 10000],
            ],
        ]);
        $html = $this->pdf->renderHtml($id);
        $this->assertStringNotContainsString('Acompte à la signature', $html);
        $this->assertStringNotContainsString('Valable', $html);
    }

    public function testQuoteValidityFollowsCompanySetting(): void
    {
        $this->pdo()->exec('UPDATE company SET quote_validity_months = 3 WHERE id = 1');
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'lines' => [
                ['label' => 'Enduit', 'quantity' => 1, 'unit' => 'u', 'unit_price_ht_cents' => 10000],
            ],
        ]);
        $html = $this->pdf->renderHtml($id);
        $this->assertStringContainsString('Valable 3 mois', $html);
        // Prérempli depuis les réglages : 30 % de 120,00 € TTC
        $this->assertStringContainsString('Acompte à la signature de 30 %, soit 36,00 €.', $html);
    }

    public function testQuoteHtmlEmbedsCompanySignature(): void
    {
        $dir = sys_get_temp_dir() . '/nf-sig-' . uniqid('', true);
        mkdir($dir);
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        file_put_contents($dir . '/signature.png', $png);
        $this->pdo()->exec("UPDATE company SET name = 'Nuances Façade', signature_path = 'signature.png' WHERE id = 1");
        $pdf = new PdfService(
            $this->pdo(),
            dirname(__DIR__, 2) . '/templates/pdf',
            dirname(__DIR__, 2) . '/storage/logos',
            $dir
        );
        $id = $this->docs->create([
            'doc_type' => 'quote',
            'client_id' => $this->clientId,
            'lines' => [
                [
                    'label' => 'Enduit',
                    'quantity' => 1,
                    'unit' => 'u',
                    'unit_price_ht_cents' => 10000,
                ],
            ],
        ]);
        $html = $pdf->renderHtml($id);
        $this->assertStringContainsString('Nuances Façade', $html);
        $this->assertStringContainsString('class="signature-img"', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Le client', $html);
        $this->assertStringContainsString('Bon pour travaux', $html);
        $this->pdo()->exec('UPDATE company SET signature_path = NULL WHERE id = 1');
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
        $this->assertStringContainsString('- 110,00 €', $html);
        $this->assertStringContainsString('Reste à payer', $html);
        $this->assertStringNotContainsString('Valable', $html);
        $this->assertStringContainsString('Paiement à réception.', $html);
        $this->assertStringNotContainsString('Bon pour travaux', $html);
    }
}
