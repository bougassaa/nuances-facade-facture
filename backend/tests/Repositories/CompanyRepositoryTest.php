<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Repositories;

use Nuances\Facture\Repositories\CompanyRepository;
use Nuances\Facture\Tests\IntegrationTestCase;

final class CompanyRepositoryTest extends IntegrationTestCase
{
    public function testUpdateAndGet(): void
    {
        $logos = dirname(__DIR__, 2) . '/storage/logos';
        $repo = new CompanyRepository($this->pdo(), $logos);

        $repo->update([
            'name' => 'Nuances Test',
            'city' => 'Lyon',
            'siret' => '123',
            'website' => 'www.nuances-facade.fr',
            'legal_form' => 'EI',
            'payment_terms' => 'Paiement à réception.',
            'vat_exempt' => true,
            'legal_decennale' => 'Assurance X',
        ]);

        $company = $repo->get();
        $this->assertSame('Nuances Test', $company['name']);
        $this->assertSame('Lyon', $company['city']);
        $this->assertSame('www.nuances-facade.fr', $company['website']);
        $this->assertSame('EI', $company['legal_form']);
        $this->assertSame('Paiement à réception.', $company['payment_terms']);
        $this->assertTrue((bool) $company['vat_exempt']);
        $this->assertSame('Assurance X', $company['legal_decennale']);
        $this->assertArrayHasKey('vat_rates', $company);
        $this->assertNotEmpty($company['vat_rates']);
        $this->assertArrayNotHasKey('logo_path', $company);
        $this->assertArrayHasKey('has_logo', $company);
        $this->assertArrayHasKey('counters', $company);
        $this->assertArrayHasKey('year', $company['counters']);
    }
}
