<?php

declare(strict_types=1);

namespace Nuances\Facture\Services;

use Nuances\Facture\Domain\DocumentType;
use PDO;

final class DocumentNumberService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Attribue le prochain numéro pour le type et l'année (verrou SQL).
     * Format: DEV-2026-001 / FAC-2026-001
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

        return sprintf('%s-%d-%03d', $type->numberPrefix(), $year, $n);
    }
}
