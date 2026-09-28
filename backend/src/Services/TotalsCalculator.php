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
            $qty = $this->parseQuantity($line['quantity']);
            $unit = $this->parseCents($line['unit_price_ht_cents']);
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

    /**
     * Quantité : nombre, ou texte avec virgule ou point.
     * « 2,5 » ne doit pas devenir 2 (ce que fait (float) en PHP).
     */
    public function parseQuantity(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            $n = (float) $value;

            return $n < 0 ? 0.0 : round($n, 4);
        }
        $normalized = $this->normalizeDecimal((string) $value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0.0;
        }
        $n = (float) $normalized;

        return $n < 0 ? 0.0 : round($n, 4);
    }

    /**
     * Centimes : entier, ou texte d’euros si le séparateur décimal est présent (« 12,50 » → 1250).
     */
    public function parseCents(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) round($value);
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0;
        }
        if (str_contains($raw, ',') || str_contains($raw, '.')) {
            return $this->eurosStringToCents($raw);
        }
        if (!is_numeric($raw)) {
            return 0;
        }

        return (int) $raw;
    }

    private function eurosStringToCents(string $raw): int
    {
        $normalized = $this->normalizeDecimal($raw);
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0;
        }
        $negative = str_starts_with($normalized, '-');
        $unsigned = $negative ? substr($normalized, 1) : $normalized;
        [$intPart, $frac] = array_pad(explode('.', $unsigned, 2), 2, '');
        $frac = substr($frac . '00', 0, 3);
        $cents = ((int) $intPart) * 100 + (int) substr($frac, 0, 2);
        if ((int) ($frac[2] ?? '0') >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    private function normalizeDecimal(string $raw): string
    {
        $s = str_replace(["\u{00A0}", "\u{202F}", ' '], '', trim($raw));
        if ($s === '') {
            return '';
        }
        $negative = str_starts_with($s, '-');
        if ($negative) {
            $s = substr($s, 1);
        }
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $dec = max($lastComma, $lastDot);
            $intPart = preg_replace('/[.,]/', '', substr($s, 0, $dec)) ?? '';
            $frac = preg_replace('/[.,]/', '', substr($s, $dec + 1)) ?? '';
            $s = $intPart . '.' . $frac;
        } else {
            $s = str_replace(',', '.', $s);
        }
        $s = preg_replace('/[^\d.]/', '', $s) ?? '';
        $dot = strpos($s, '.');
        if ($dot !== false) {
            $s = substr($s, 0, $dot + 1) . str_replace('.', '', substr($s, $dot + 1));
        }
        if ($s === '' || $s === '.') {
            return '';
        }

        return $negative ? '-' . $s : $s;
    }

    /** Reste à payer = TTC − déduction (jamais négatif). */
    public function remainingDueCents(int $totalTtcCents, int $deductionTtcCents): int
    {
        return max(0, $totalTtcCents - $deductionTtcCents);
    }
}
