<?php

declare(strict_types=1);

namespace Nuances\Facture\Services;

/**
 * Calculs monétaires en centimes.
 * Arrondi HT à la ligne : round(qty * unit_ht_cents), puis somme.
 * TVA une seule fois sur le total HT du document.
 */
final class TotalsCalculator
{
    /**
     * @param list<array{quantity: float|string, unit_price_ht_cents: int}> $lines
     * @return array{
     *   lines: list<array{line_ht_cents: int, line_vat_cents: int}>,
     *   total_ht_cents: int,
     *   total_vat_cents: int,
     *   total_ttc_cents: int
     * }
     */
    public function compute(array $lines, int $vatRateBp = 2000, bool $vatExempt = false): array
    {
        $computed = [];
        $totalHt = 0;
        $rateBp = $vatExempt ? 0 : $vatRateBp;

        foreach ($lines as $line) {
            $qty = (float) $line['quantity'];
            $unit = (int) $line['unit_price_ht_cents'];
            $lineHt = (int) round($qty * $unit);

            $computed[] = [
                'line_ht_cents' => $lineHt,
                'line_vat_cents' => 0,
            ];
            $totalHt += $lineHt;
        }

        $totalVat = (int) round($totalHt * $rateBp / 10000);

        return [
            'lines' => $computed,
            'total_ht_cents' => $totalHt,
            'total_vat_cents' => $totalVat,
            'total_ttc_cents' => $totalHt + $totalVat,
        ];
    }

    /** Reste à payer = TTC − déduction (jamais négatif). */
    public function remainingDueCents(int $totalTtcCents, int $deductionTtcCents): int
    {
        return max(0, $totalTtcCents - $deductionTtcCents);
    }
}
