<?php

declare(strict_types=1);

namespace Nuances\Facture\Repositories;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class LineDesignationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array> */
    public function list(): array
    {
        return $this->pdo
            ->query('SELECT * FROM line_designations ORDER BY label ASC')
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM line_designations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByLabel(string $label): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM line_designations WHERE label = :label');
        $stmt->execute(['label' => $label]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        [$label, $price] = $this->normalize($data);
        if ($this->findByLabel($label) !== null) {
            throw new RuntimeException('Cette désignation existe déjà');
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO line_designations (label, unit_price_ht_cents) VALUES (:label, :price)'
            );
            $stmt->execute(['label' => $label, 'price' => $price]);
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                throw new RuntimeException('Cette désignation existe déjà');
            }
            throw $e;
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if ($this->find($id) === null) {
            throw new RuntimeException('Désignation introuvable');
        }
        [$label, $price] = $this->normalize($data);
        $existing = $this->findByLabel($label);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            throw new RuntimeException('Cette désignation existe déjà');
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE line_designations SET label = :label, unit_price_ht_cents = :price WHERE id = :id'
            );
            $stmt->execute(['label' => $label, 'price' => $price, 'id' => $id]);
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                throw new RuntimeException('Cette désignation existe déjà');
            }
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        if ($this->find($id) === null) {
            throw new RuntimeException('Désignation introuvable');
        }
        $stmt = $this->pdo->prepare('DELETE FROM line_designations WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Insère les libellés absents du catalogue. Ne met jamais à jour un prix existant.
     * Pour un même libellé répété, le prix de la première occurrence est utilisé.
     *
     * @param list<array{label: string, unit_price_ht_cents: int}> $lines
     */
    public function ensureMissingFromLines(array $lines): void
    {
        $seen = [];
        $ins = $this->pdo->prepare(
            'INSERT IGNORE INTO line_designations (label, unit_price_ht_cents) VALUES (:label, :price)'
        );
        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $price = max(0, (int) ($line['unit_price_ht_cents'] ?? 0));
            $ins->execute(['label' => $label, 'price' => $price]);
        }
    }

    /** @return array{0: string, 1: int} */
    private function normalize(array $data): array
    {
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new InvalidArgumentException('La désignation est obligatoire');
        }
        if (mb_strlen($label) > 500) {
            throw new InvalidArgumentException('Désignation trop longue (500 caractères max)');
        }
        $price = (int) ($data['unit_price_ht_cents'] ?? 0);
        if ($price < 0) {
            throw new InvalidArgumentException('Le prix unitaire HT ne peut pas être négatif');
        }
        return [$label, $price];
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        return $e->getCode() === '23000' || str_contains($e->getMessage(), '1062');
    }
}
