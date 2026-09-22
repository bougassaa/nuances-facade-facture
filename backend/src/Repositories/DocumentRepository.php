<?php

declare(strict_types=1);

namespace Nuances\Facture\Repositories;

use Nuances\Facture\Domain\DocumentStatus;
use Nuances\Facture\Domain\DocumentType;
use Nuances\Facture\Services\DocumentNumberService;
use Nuances\Facture\Services\TotalsCalculator;
use PDO;
use RuntimeException;
use InvalidArgumentException;

final class DocumentRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly TotalsCalculator $totals,
        private readonly DocumentNumberService $numbers,
    ) {
    }

    public function list(?string $docType = null, int $limit = 50): array
    {
        $sql = 'SELECT d.*, c.name AS client_name
                FROM documents d
                JOIN clients c ON c.id = d.client_id';
        $params = [];
        if ($docType !== null && $docType !== '') {
            $sql .= ' WHERE d.doc_type = :type';
            $params['type'] = $docType;
        }
        $sql .= ' ORDER BY d.updated_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, c.name AS client_name FROM documents d
             JOIN clients c ON c.id = d.client_id WHERE d.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $doc = $stmt->fetch();
        if ($doc === false) {
            return null;
        }
        $doc['lines'] = $this->lines($id);
        return $doc;
    }

    /** @return list<array> */
    public function lines(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_lines WHERE document_id = :id ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['id' => $documentId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $type = DocumentType::from((string) $data['doc_type']);
        $clientId = (int) $data['client_id'];
        $this->assertClient($clientId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO documents (doc_type, status, client_id, object, notes, valid_until)
                 VALUES (:type, :status, :client, :object, :notes, :valid)'
            );
            $stmt->execute([
                'type' => $type->value,
                'status' => DocumentStatus::Draft->value,
                'client' => $clientId,
                'object' => (string) ($data['object'] ?? ''),
                'notes' => $data['notes'] ?? null,
                'valid' => $data['valid_until'] ?? null,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->replaceLines($id, $data['lines'] ?? []);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): void
    {
        $doc = $this->require($id);
        $status = DocumentStatus::from((string) $doc['status']);
        if (!$status->isEditable()) {
            throw new RuntimeException('Document non modifiable (déjà envoyé)');
        }

        $clientId = (int) ($data['client_id'] ?? $doc['client_id']);
        $this->assertClient($clientId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE documents SET client_id = :client, object = :object, notes = :notes,
                 valid_until = :valid WHERE id = :id'
            );
            $stmt->execute([
                'client' => $clientId,
                'object' => (string) ($data['object'] ?? ''),
                'notes' => $data['notes'] ?? null,
                'valid' => $data['valid_until'] ?? null,
                'id' => $id,
            ]);
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($id, $data['lines'] ?? []);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $doc = $this->require($id);
        if (DocumentStatus::from((string) $doc['status']) !== DocumentStatus::Draft) {
            throw new RuntimeException('Seuls les brouillons peuvent être supprimés');
        }
        $stmt = $this->pdo->prepare('DELETE FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Passe en envoyé, attribue un numéro, retourne le document. */
    public function send(int $id): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $doc = $stmt->fetch();
            if ($doc === false) {
                throw new RuntimeException('Document introuvable');
            }
            $status = DocumentStatus::from((string) $doc['status']);
            if ($status !== DocumentStatus::Draft) {
                throw new RuntimeException('Seuls les brouillons peuvent être envoyés');
            }
            $lines = $this->lines($id);
            if ($lines === []) {
                throw new RuntimeException('Ajoutez au moins une ligne avant d’envoyer');
            }

            $type = DocumentType::from((string) $doc['doc_type']);
            $year = (int) date('Y');
            $number = $this->numbers->next($type, $year);
            $today = date('Y-m-d');

            $upd = $this->pdo->prepare(
                'UPDATE documents SET status = :status, number = :number, issue_date = :issue,
                 sent_at = NOW() WHERE id = :id'
            );
            $upd->execute([
                'status' => DocumentStatus::Sent->value,
                'number' => $number,
                'issue' => $today,
                'id' => $id,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $found = $this->find($id);
        if ($found === null) {
            throw new RuntimeException('Document introuvable après envoi');
        }
        return $found;
    }

    public function setQuoteStatus(int $id, DocumentStatus $newStatus): array
    {
        $doc = $this->require($id);
        $type = DocumentType::from((string) $doc['doc_type']);
        if ($type !== DocumentType::Quote) {
            throw new RuntimeException('Statut réservé aux devis');
        }
        DocumentStatus::assertAllowed($type, $newStatus);
        if (!in_array($newStatus, [DocumentStatus::Accepted, DocumentStatus::Rejected, DocumentStatus::Sent], true)) {
            throw new InvalidArgumentException('Transition de statut invalide');
        }
        $current = DocumentStatus::from((string) $doc['status']);
        if ($current === DocumentStatus::Draft) {
            throw new RuntimeException('Le devis doit d’abord être envoyé');
        }

        $stmt = $this->pdo->prepare('UPDATE documents SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $newStatus->value, 'id' => $id]);
        $found = $this->find($id);
        if ($found === null) {
            throw new RuntimeException('Document introuvable');
        }
        return $found;
    }

    /** Convertit un devis accepté en facture brouillon. */
    public function convertToInvoice(int $quoteId): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $quoteId]);
            $doc = $stmt->fetch();
            if ($doc === false) {
                throw new RuntimeException('Devis introuvable');
            }
            if ($doc['doc_type'] !== DocumentType::Quote->value) {
                throw new RuntimeException('Seul un devis peut être converti');
            }
            if ($doc['status'] !== DocumentStatus::Accepted->value) {
                throw new RuntimeException('Le devis doit être accepté');
            }

            $ins = $this->pdo->prepare(
                'INSERT INTO documents (doc_type, status, client_id, source_document_id, object, notes)
                 VALUES (:type, :status, :client, :source, :object, :notes)'
            );
            $ins->execute([
                'type' => DocumentType::Invoice->value,
                'status' => DocumentStatus::Draft->value,
                'client' => $doc['client_id'],
                'source' => $quoteId,
                'object' => $doc['object'],
                'notes' => $doc['notes'],
            ]);
            $invoiceId = (int) $this->pdo->lastInsertId();

            $lines = $this->lines($quoteId);
            $payload = [];
            foreach ($lines as $line) {
                $payload[] = [
                    'label' => $line['label'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price_ht_cents' => (int) $line['unit_price_ht_cents'],
                    'vat_rate_bp' => (int) $line['vat_rate_bp'],
                ];
            }
            $this->replaceLines($invoiceId, $payload);
            $this->pdo->commit();
            return $invoiceId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function require(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $doc = $stmt->fetch();
        if ($doc === false) {
            throw new RuntimeException('Document introuvable');
        }
        return $doc;
    }

    private function assertClient(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetch() === false) {
            throw new InvalidArgumentException('Client introuvable');
        }
    }

    /** @param list<array> $lines */
    private function replaceLines(int $documentId, array $lines): void
    {
        $del = $this->pdo->prepare('DELETE FROM document_lines WHERE document_id = :id');
        $del->execute(['id' => $documentId]);

        $vatExempt = (bool) $this->pdo->query('SELECT vat_exempt FROM company WHERE id = 1')->fetchColumn();
        $normalized = [];
        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $normalized[] = [
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit_price_ht_cents' => (int) ($line['unit_price_ht_cents'] ?? 0),
                'vat_rate_bp' => $vatExempt ? 0 : (int) ($line['vat_rate_bp'] ?? 2000),
                'label' => $label,
                'unit' => trim((string) ($line['unit'] ?? 'u')) ?: 'u',
            ];
        }

        $computed = $this->totals->compute($normalized, $vatExempt);
        $ins = $this->pdo->prepare(
            'INSERT INTO document_lines
             (document_id, position, label, quantity, unit, unit_price_ht_cents, vat_rate_bp, line_ht_cents, line_vat_cents)
             VALUES (:doc, :pos, :label, :qty, :unit, :price, :vat, :ht, :lvat)'
        );
        foreach ($normalized as $i => $line) {
            $ins->execute([
                'doc' => $documentId,
                'pos' => $i,
                'label' => $line['label'],
                'qty' => $line['quantity'],
                'unit' => $line['unit'],
                'price' => $line['unit_price_ht_cents'],
                'vat' => $line['vat_rate_bp'],
                'ht' => $computed['lines'][$i]['line_ht_cents'],
                'lvat' => $computed['lines'][$i]['line_vat_cents'],
            ]);
        }

        $upd = $this->pdo->prepare(
            'UPDATE documents SET total_ht_cents = :ht, total_vat_cents = :vat, total_ttc_cents = :ttc
             WHERE id = :id'
        );
        $upd->execute([
            'ht' => $computed['total_ht_cents'],
            'vat' => $computed['total_vat_cents'],
            'ttc' => $computed['total_ttc_cents'],
            'id' => $documentId,
        ]);
    }
}
