<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Repositories;

use InvalidArgumentException;
use Nuances\Facture\Repositories\ClientRepository;
use Nuances\Facture\Repositories\DocumentRepository;
use Nuances\Facture\Repositories\LineDesignationRepository;
use Nuances\Facture\Services\DocumentNumberService;
use Nuances\Facture\Services\TotalsCalculator;
use Nuances\Facture\Tests\IntegrationTestCase;
use RuntimeException;

final class LineDesignationRepositoryTest extends IntegrationTestCase
{
    private LineDesignationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTable();
        $this->purgeBusinessData();
        $this->repo = new LineDesignationRepository($this->pdo());
    }

    private function ensureTable(): void
    {
        $this->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS line_designations (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              label VARCHAR(500) NOT NULL,
              unit_price_ht_cents INT NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_line_designations_label (label)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function testCreateFindUpdateDelete(): void
    {
        $id = $this->repo->create([
            'label' => 'Enduit monocouche',
            'unit_price_ht_cents' => 4500,
        ]);
        $this->assertGreaterThan(0, $id);

        $found = $this->repo->find($id);
        $this->assertNotNull($found);
        $this->assertSame('Enduit monocouche', $found['label']);
        $this->assertSame(4500, (int) $found['unit_price_ht_cents']);

        $this->repo->update($id, [
            'label' => 'Enduit monocouche',
            'unit_price_ht_cents' => 4800,
        ]);
        $this->assertSame(4800, (int) $this->repo->find($id)['unit_price_ht_cents']);

        $this->repo->delete($id);
        $this->assertNull($this->repo->find($id));
    }

    public function testDuplicateLabelRejected(): void
    {
        $this->repo->create(['label' => 'Peinture façade', 'unit_price_ht_cents' => 1000]);
        $this->expectException(RuntimeException::class);
        $this->repo->create(['label' => 'peinture façade', 'unit_price_ht_cents' => 2000]);
    }

    public function testLabelRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repo->create(['label' => '  ', 'unit_price_ht_cents' => 0]);
    }

    public function testEnsureMissingFromLinesAddsNewWithoutOverwriting(): void
    {
        $this->repo->create(['label' => 'Existante', 'unit_price_ht_cents' => 3000]);

        $this->repo->ensureMissingFromLines([
            ['label' => 'Existante', 'unit_price_ht_cents' => 9999],
            ['label' => 'Nouvelle', 'unit_price_ht_cents' => 1500],
            ['label' => 'Nouvelle', 'unit_price_ht_cents' => 2500],
        ]);

        $existing = $this->repo->findByLabel('Existante');
        $this->assertNotNull($existing);
        $this->assertSame(3000, (int) $existing['unit_price_ht_cents']);

        $created = $this->repo->findByLabel('Nouvelle');
        $this->assertNotNull($created);
        $this->assertSame(1500, (int) $created['unit_price_ht_cents']);
    }

    public function testSavingDocumentAddsUnknownDesignations(): void
    {
        $this->repo->create(['label' => 'Déjà connue', 'unit_price_ht_cents' => 2000]);

        $clients = new ClientRepository($this->pdo());
        $clientId = $clients->create(['name' => 'Client Test']);
        $docs = new DocumentRepository(
            $this->pdo(),
            new TotalsCalculator(),
            new DocumentNumberService($this->pdo()),
            $this->repo,
        );
        $docs->create([
            'doc_type' => 'quote',
            'client_id' => $clientId,
            'object' => 'Chantier test',
            'lines' => [
                [
                    'label' => 'Déjà connue',
                    'quantity' => 1,
                    'unit' => 'm²',
                    'unit_price_ht_cents' => 9999,
                ],
                [
                    'label' => 'Ravalement',
                    'quantity' => 10,
                    'unit' => 'm²',
                    'unit_price_ht_cents' => 5500,
                ],
            ],
        ]);

        $known = $this->repo->findByLabel('Déjà connue');
        $this->assertSame(2000, (int) $known['unit_price_ht_cents']);

        $added = $this->repo->findByLabel('Ravalement');
        $this->assertNotNull($added);
        $this->assertSame(5500, (int) $added['unit_price_ht_cents']);
    }
}
