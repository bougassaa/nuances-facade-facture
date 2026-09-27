<?php

declare(strict_types=1);

namespace Nuances\Facture\Services;

/**
 * Calculs monétaires en centimes.
 * Arrondi à la ligne : round(qty * unit_ht_cents).
 * TVA par ligne puis somme.
 */
final class TotalsCalculator
{
    /**
     * @param list<array{quantity: float|string, unit_price_ht_cents: int, vat_rate_bp: int}> $lines
     * @return array{
     *   lines: list<array{line_ht_cents: int, line_vat_cents: int}>,
     *   total_ht_cents: int,
     *   total_vat_cents: int,
     *   total_ttc_cents: int,
     *   vat_by_rate: list<array{vat_rate_bp: int, vat_cents: int, ht_cents: int}>
     * }
     */
    public function compute(array $lines, bool $vatExempt = false): array
    {
        $computed = [];
        $totalHt = 0;
        $totalVat = 0;
        /** @var array<int, array{vat_rate_bp: int, vat_cents: int, ht_cents: int}> $byRate */
        $byRate = [];

        foreach ($lines as $line) {
            $qty = (float) $line['quantity'];
            $unit = (int) $line['unit_price_ht_cents'];
            $rateBp = $vatExempt ? 0 : (int) $line['vat_rate_bp'];

            $lineHt = (int) round($qty * $unit);
            $lineVat = (int) round($lineHt * $rateBp / 10000);

            $computed[] = [
                'line_ht_cents' => $lineHt,
                'line_vat_cents' => $lineVat,
            ];
            $totalHt += $lineHt;
            $totalVat += $lineVat;

            if (!isset($byRate[$rateBp])) {
                $byRate[$rateBp] = [
                    'vat_rate_bp' => $rateBp,
                    'vat_cents' => 0,
                    'ht_cents' => 0,
                ];
            }
            $byRate[$rateBp]['ht_cents'] += $lineHt;
            $byRate[$rateBp]['vat_cents'] += $lineVat;
        }

        ksort($byRate);

        return [
            'lines' => $computed,
            'total_ht_cents' => $totalHt,
            'total_vat_cents' => $totalVat,
            'total_ttc_cents' => $totalHt + $totalVat,
            'vat_by_rate' => array_values($byRate),
        ];
    }

    /** Montant d'acompte devis = round(TTC * percent / 100). */
    public function depositAmountCents(int $totalTtcCents, float $percent): int
    {
        if ($percent <= 0) {
            return 0;
        }
        return (int) round($totalTtcCents * $percent / 100);
    }

    /** Reste à payer = TTC − déduction (jamais négatif). */
    public function remainingDueCents(int $totalTtcCents, int $deductionTtcCents): int
    {
        return max(0, $totalTtcCents - $deductionTtcCents);
    }
}
