<?php

declare(strict_types=1);

namespace Nuances\Facture\Services;

use Nuances\Facture\Domain\DocumentType;
use PDO;
use RuntimeException;

final class DocumentNumberService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Attribue le prochain numéro pour le type et l'année (verrou SQL).
     * Format: DEV-2026-0001 / FAC-2026-0001
     */
    public function next(DocumentType $type, int $year): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT last_number FROM counters WHERE doc_type = :type AND year = :year FOR UPDATE'
        );
        $stmt->execute(['type' => $type->value, 'year' => $year]);
        $row = $stmt->fetch();

        if ($row === false) {
            $insert = $this->pdo->prepare(
                'INSERT INTO counters (doc_type, year, last_number) VALUES (:type, :year, 1)'
            );
            $insert->execute(['type' => $type->value, 'year' => $year]);
            $n = 1;
        } else {
            $n = (int) $row['last_number'] + 1;
            $update = $this->pdo->prepare(
                'UPDATE counters SET last_number = :n WHERE doc_type = :type AND year = :year'
            );
            $update->execute(['n' => $n, 'type' => $type->value, 'year' => $year]);
        }

        return sprintf('%s-%d-%04d', $type->numberPrefix(), $year, $n);
    }

    /**
     * Initialise le compteur de l'année uniquement s'il n'existe pas encore
     * et qu'aucun document de ce type n'a déjà un numéro pour l'année.
     */
    public function seedIfEmpty(DocumentType $type, int $year, int $lastNumber): void
    {
        if ($lastNumber < 0) {
            throw new RuntimeException('Le dernier numéro doit être positif ou zéro');
        }

        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare(
                'SELECT last_number FROM counters WHERE doc_type = :type AND year = :year FOR UPDATE'
            );
            $lock->execute(['type' => $type->value, 'year' => $year]);
            $existing = $lock->fetch();

            $hasNumbered = $this->pdo->prepare(
                "SELECT COUNT(*) FROM documents
                 WHERE doc_type = :type AND number IS NOT NULL AND number LIKE :prefix"
            );
            $prefix = $type->numberPrefix() . '-' . $year . '-%';
            $hasNumbered->execute(['type' => $type->value, 'prefix' => $prefix]);
            $count = (int) $hasNumbered->fetchColumn();

            if ($existing !== false || $count > 0) {
                // Ne pas écraser une séquence déjà démarrée
                $this->pdo->commit();
                return;
            }

            if ($lastNumber === 0) {
                $this->pdo->commit();
                return;
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO counters (doc_type, year, last_number) VALUES (:type, :year, :n)'
            );
            $insert->execute([
                'type' => $type->value,
                'year' => $year,
                'n' => $lastNumber,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
