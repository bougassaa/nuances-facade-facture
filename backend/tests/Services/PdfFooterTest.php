<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Services;

use Nuances\Facture\Services\PdfService;
use PHPUnit\Framework\TestCase;

final class PdfFooterTest extends TestCase
{
    public function testFooterTextsMatchArtisanLayout(): void
    {
        [$contact, $legal] = PdfService::footerTexts([
            'name' => 'Nuances Façade',
            'address_line1' => 'Résidence Le Sevigne',
            'address_line2' => 'Avenue Maréchal Leclerc',
            'postal_code' => '84600',
            'city' => 'Valréas',
            'phone' => '0651388181',
            'email' => 'contact@nuances-facade.fr',
            'legal_form' => 'EI',
            'siret' => '94987277400011',
            'legal_decennale' => "ERGO ASSURANCES\nSV75020721/09686",
            'iban' => 'FR78 3000 2042 4300 0007 0927 A04',
        ]);

        $this->assertSame(
            'Nuances Façade — Résidence Le Sevigne, Avenue Maréchal Leclerc, 84600 Valréas, France'
            . ' — Téléphone : 0651388181 — e-mail : contact@nuances-facade.fr',
            $contact
        );
        $this->assertSame(
            'EI — SIRET : 94987277400011 — Assurance : ERGO ASSURANCES SV75020721/09686'
            . ' — IBAN : FR78 3000 2042 4300 0007 0927 A04',
            $legal
        );
    }

    public function testFooterTextsSkipMissingFields(): void
    {
        [$contact, $legal] = PdfService::footerTexts(['name' => 'Nuances Façade', 'city' => 'Orange']);

        $this->assertSame('Nuances Façade — Orange, France', $contact);
        $this->assertSame('EI', $legal);
    }
}
