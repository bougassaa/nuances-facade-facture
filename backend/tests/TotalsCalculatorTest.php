<?php

declare(strict_types=1);

use Nuances\Facture\Services\TotalsCalculator;
use PHPUnit\Framework\TestCase;

final class TotalsCalculatorTest extends TestCase
{
    public function testLineRoundingAndVat(): void
    {
        $calc = new TotalsCalculator();
        $result = $calc->compute([
            ['quantity' => 2.5, 'unit_price_ht_cents' => 10000, 'vat_rate_bp' => 2000],
            ['quantity' => 1, 'unit_price_ht_cents' => 3333, 'vat_rate_bp' => 1000],
        ]);

        $this->assertSame(25000, $result['lines'][0]['line_ht_cents']);
        $this->assertSame(5000, $result['lines'][0]['line_vat_cents']);
        $this->assertSame(3333, $result['lines'][1]['line_ht_cents']);
        $this->assertSame(333, $result['lines'][1]['line_vat_cents']);
        $this->assertSame(28333, $result['total_ht_cents']);
        $this->assertSame(5333, $result['total_vat_cents']);
        $this->assertSame(33666, $result['total_ttc_cents']);
    }

    public function testVatExempt(): void
    {
        $calc = new TotalsCalculator();
        $result = $calc->compute([
            ['quantity' => 1, 'unit_price_ht_cents' => 10000, 'vat_rate_bp' => 2000],
        ], true);

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
            ['quantity' => 10, 'unit_price_ht_cents' => 1000, 'vat_rate_bp' => 550],
        ]);
        $this->assertSame(10000, $result['total_ht_cents']);
        $this->assertSame(550, $result['total_vat_cents']);
        $this->assertSame(10550, $result['total_ttc_cents']);
    }

    public function testVatByRateGrouped(): void
    {
        $result = (new TotalsCalculator())->compute([
            ['quantity' => 1, 'unit_price_ht_cents' => 10000, 'vat_rate_bp' => 1000],
            ['quantity' => 1, 'unit_price_ht_cents' => 20000, 'vat_rate_bp' => 1000],
            ['quantity' => 1, 'unit_price_ht_cents' => 5000, 'vat_rate_bp' => 2000],
        ]);
        $this->assertCount(2, $result['vat_by_rate']);
        $this->assertSame(1000, $result['vat_by_rate'][0]['vat_rate_bp']);
        $this->assertSame(3000, $result['vat_by_rate'][0]['vat_cents']);
        $this->assertSame(2000, $result['vat_by_rate'][1]['vat_rate_bp']);
        $this->assertSame(1000, $result['vat_by_rate'][1]['vat_cents']);
    }

    public function testDepositAmountCents(): void
    {
        $calc = new TotalsCalculator();
        // 1647250 * 30% = 494175
        $this->assertSame(494175, $calc->depositAmountCents(1647250, 30));
        $this->assertSame(0, $calc->depositAmountCents(10000, 0));
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
            ['quantity' => 1.333, 'unit_price_ht_cents' => 100, 'vat_rate_bp' => 0],
        ]);
        $this->assertSame(133, $result['lines'][0]['line_ht_cents']);
    }
}
