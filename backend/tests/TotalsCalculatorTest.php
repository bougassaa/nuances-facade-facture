<?php

declare(strict_types=1);

use Nuances\Facture\Services\TotalsCalculator;
use PHPUnit\Framework\TestCase;

final class TotalsCalculatorTest extends TestCase
{
    public function testLineRoundingAndDocumentVat(): void
    {
        $calc = new TotalsCalculator();
        // HT: 25000 + 3333 = 28333 ; TVA 10 % sur total HT = round(28333 * 1000 / 10000) = 2833
        $result = $calc->compute([
            ['quantity' => 2.5, 'unit_price_ht_cents' => 10000],
            ['quantity' => 1, 'unit_price_ht_cents' => 3333],
        ], 1000);

        $this->assertSame(25000, $result['lines'][0]['line_ht_cents']);
        $this->assertSame(0, $result['lines'][0]['line_vat_cents']);
        $this->assertSame(3333, $result['lines'][1]['line_ht_cents']);
        $this->assertSame(0, $result['lines'][1]['line_vat_cents']);
        $this->assertSame(28333, $result['total_ht_cents']);
        $this->assertSame(2833, $result['total_vat_cents']);
        $this->assertSame(31166, $result['total_ttc_cents']);
    }

    public function testVatExempt(): void
    {
        $calc = new TotalsCalculator();
        $result = $calc->compute([
            ['quantity' => 1, 'unit_price_ht_cents' => 10000],
        ], 2000, true);

        $this->assertSame(0, $result['total_vat_cents']);
        $this->assertSame(10000, $result['total_ttc_cents']);
    }

    public function testEmptyLines(): void
    {
        $result = (new TotalsCalculator())->compute([]);
        $this->assertSame(0, $result['total_ht_cents']);
        $this->assertSame(0, $result['total_vat_cents']);
        $this->assertSame(0, $result['total_ttc_cents']);
        $this->assertSame([], $result['lines']);
    }

    public function testReducedRate55(): void
    {
        $result = (new TotalsCalculator())->compute([
            ['quantity' => 10, 'unit_price_ht_cents' => 1000],
        ], 550);
        $this->assertSame(10000, $result['total_ht_cents']);
        $this->assertSame(550, $result['total_vat_cents']);
        $this->assertSame(10550, $result['total_ttc_cents']);
    }

    public function testSingleVatOnSummedHt(): void
    {
        // Deux lignes HT 10000 + 20000 = 30000 ; TVA 20 % = 6000 (un seul arrondi)
        $result = (new TotalsCalculator())->compute([
            ['quantity' => 1, 'unit_price_ht_cents' => 10000],
            ['quantity' => 1, 'unit_price_ht_cents' => 20000],
        ], 2000);
        $this->assertSame(30000, $result['total_ht_cents']);
        $this->assertSame(6000, $result['total_vat_cents']);
        $this->assertSame(36000, $result['total_ttc_cents']);
    }

    public function testRemainingDueCents(): void
    {
        $calc = new TotalsCalculator();
        $this->assertSame(346610, $calc->remainingDueCents(515900, 169290));
        $this->assertSame(0, $calc->remainingDueCents(100, 200));
    }

    public function testFractionalQuantityRoundsPerLine(): void
    {
        // 1.333 * 100 = 133.3 → 133
        $result = (new TotalsCalculator())->compute([
            ['quantity' => 1.333, 'unit_price_ht_cents' => 100],
        ], 0);
        $this->assertSame(133, $result['lines'][0]['line_ht_cents']);
    }
}
