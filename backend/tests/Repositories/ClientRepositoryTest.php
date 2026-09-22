<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Repositories;

use Nuances\Facture\Repositories\ClientRepository;
use Nuances\Facture\Tests\IntegrationTestCase;
use RuntimeException;

final class ClientRepositoryTest extends IntegrationTestCase
{
    private ClientRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purgeBusinessData();
        $this->repo = new ClientRepository($this->pdo());
    }

    public function testCreateFindUpdateDelete(): void
    {
        $id = $this->repo->create([
            'name' => 'Martin Claire',
            'city' => 'Lyon',
            'email' => 'claire@example.com',
        ]);
        $this->assertGreaterThan(0, $id);

        $found = $this->repo->find($id);
        $this->assertNotNull($found);
        $this->assertSame('Martin Claire', $found['name']);

        $this->repo->update($id, [
            'name' => 'Martin C.',
            'city' => 'Villeurbanne',
            'email' => 'claire@example.com',
        ]);
        $this->assertSame('Martin C.', $this->repo->find($id)['name']);

        $this->repo->delete($id);
        $this->assertNull($this->repo->find($id));
    }

    public function testNameRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->create(['name' => '  ']);
    }

    public function testSearchByName(): void
    {
        $this->repo->create(['name' => 'Alpha SARL']);
        $this->repo->create(['name' => 'Beta SAS']);
        $items = $this->repo->list('Alpha');
        $this->assertCount(1, $items);
        $this->assertSame('Alpha SARL', $items[0]['name']);
    }

    public function testCannotDeleteClientWithDocuments(): void
    {
        $clientId = $this->repo->create(['name' => 'Lié Doc']);
        $docs = new \Nuances\Facture\Repositories\DocumentRepository(
            $this->pdo(),
            new \Nuances\Facture\Services\TotalsCalculator(),
            new \Nuances\Facture\Services\DocumentNumberService($this->pdo())
        );
        $docs->create([
            'doc_type' => 'quote',
            'client_id' => $clientId,
            'object' => 'Test',
            'lines' => [
                ['label' => 'Ligne', 'quantity' => 1, 'unit' => 'u', 'unit_price_ht_cents' => 1000, 'vat_rate_bp' => 2000],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->repo->delete($clientId);
    }
}
