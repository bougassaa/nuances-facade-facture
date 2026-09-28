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
    /** @var list<int> */
    private const ALLOWED_VAT_RATES = [0, 550, 1000, 2000];

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
        $doc['remaining_due_cents'] = $this->totals->remainingDueCents(
            (int) $doc['total_ttc_cents'],
            (int) $doc['deduction_ttc_cents']
        );
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
        $fields = $this->documentFields($type, $data);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO documents (
                    doc_type, status, client_id, object, notes, valid_until,
                    site_address_line1, site_address_line2, site_postal_code, site_city,
                    vat_rate_bp, deposit_ttc_cents, deduction_label, deduction_ttc_cents
                 ) VALUES (
                    :type, :status, :client, :object, :notes, :valid,
                    :sa1, :sa2, :scp, :scity,
                    :vat, :deposit, :ded_label, :ded_cents
                 )'
            );
            $stmt->execute([
                'type' => $type->value,
                'status' => DocumentStatus::Draft->value,
                'client' => $clientId,
                'object' => $fields['object'],
                'notes' => $fields['notes'],
                'valid' => $fields['valid_until'],
                'sa1' => $fields['site_address_line1'],
                'sa2' => $fields['site_address_line2'],
                'scp' => $fields['site_postal_code'],
                'scity' => $fields['site_city'],
                'vat' => $fields['vat_rate_bp'],
                'deposit' => $fields['deposit_ttc_cents'],
                'ded_label' => $fields['deduction_label'],
                'ded_cents' => $fields['deduction_ttc_cents'],
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $limitCents = $type === DocumentType::Quote
                ? $fields['deposit_ttc_cents']
                : $fields['deduction_ttc_cents'];
            $this->replaceLines($id, $data['lines'] ?? [], $fields['vat_rate_bp'], $limitCents, $type);
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
        $type = DocumentType::from((string) $doc['doc_type']);
        $fields = $this->documentFields($type, $data, $doc);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE documents SET client_id = :client, object = :object, notes = :notes,
                 valid_until = :valid,
                 site_address_line1 = :sa1, site_address_line2 = :sa2,
                 site_postal_code = :scp, site_city = :scity,
                 vat_rate_bp = :vat, deposit_ttc_cents = :deposit,
                 deduction_label = :ded_label, deduction_ttc_cents = :ded_cents
                 WHERE id = :id'
            );
            $stmt->execute([
                'client' => $clientId,
                'object' => $fields['object'],
                'notes' => $fields['notes'],
                'valid' => $fields['valid_until'],
                'sa1' => $fields['site_address_line1'],
                'sa2' => $fields['site_address_line2'],
                'scp' => $fields['site_postal_code'],
                'scity' => $fields['site_city'],
                'vat' => $fields['vat_rate_bp'],
                'deposit' => $fields['deposit_ttc_cents'],
                'ded_label' => $fields['deduction_label'],
                'ded_cents' => $fields['deduction_ttc_cents'],
                'id' => $id,
            ]);
            $limitCents = $type === DocumentType::Quote
                ? $fields['deposit_ttc_cents']
                : $fields['deduction_ttc_cents'];
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($id, $data['lines'] ?? [], $fields['vat_rate_bp'], $limitCents, $type);
            } else {
                $this->recomputeTotals($id, $fields['vat_rate_bp'], $limitCents, $type);
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

    /** Convertit un devis accepté en facture brouillon (copie chantier + TVA, pas l’acompte). */
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
                'INSERT INTO documents (
                    doc_type, status, client_id, source_document_id, object, notes,
                    site_address_line1, site_address_line2, site_postal_code, site_city,
                    vat_rate_bp
                 ) VALUES (
                    :type, :status, :client, :source, :object, :notes,
                    :sa1, :sa2, :scp, :scity,
                    :vat
                 )'
            );
            $ins->execute([
                'type' => DocumentType::Invoice->value,
                'status' => DocumentStatus::Draft->value,
                'client' => $doc['client_id'],
                'source' => $quoteId,
                'object' => $doc['object'],
                'notes' => $doc['notes'],
                'sa1' => $doc['site_address_line1'] ?? '',
                'sa2' => $doc['site_address_line2'] ?? '',
                'scp' => $doc['site_postal_code'] ?? '',
                'scity' => $doc['site_city'] ?? '',
                'vat' => (int) ($doc['vat_rate_bp'] ?? 2000),
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
                ];
            }
            $this->replaceLines(
                $invoiceId,
                $payload,
                (int) ($doc['vat_rate_bp'] ?? 2000),
                0,
                DocumentType::Invoice
            );
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

    /**
     * @param array|null $existing
     * @return array{
     *   object: string,
     *   notes: ?string,
     *   valid_until: ?string,
     *   site_address_line1: string,
     *   site_address_line2: string,
     *   site_postal_code: string,
     *   site_city: string,
     *   vat_rate_bp: int,
     *   deposit_ttc_cents: int,
     *   deduction_label: string,
     *   deduction_ttc_cents: int
     * }
     */
    private function documentFields(DocumentType $type, array $data, ?array $existing = null): array
    {
        $vatExempt = (bool) $this->pdo->query('SELECT vat_exempt FROM company WHERE id = 1')->fetchColumn();
        $vatRate = (int) ($data['vat_rate_bp'] ?? $existing['vat_rate_bp'] ?? 2000);
        if ($vatExempt) {
            $vatRate = 0;
        } elseif (!in_array($vatRate, self::ALLOWED_VAT_RATES, true)) {
            throw new InvalidArgumentException('Taux de TVA invalide');
        }

        $deposit = $this->totals->parseCents($data['deposit_ttc_cents'] ?? $existing['deposit_ttc_cents'] ?? 0);
        if ($deposit < 0) {
            throw new InvalidArgumentException('L’acompte ne peut pas être négatif');
        }

        $deduction = $this->totals->parseCents($data['deduction_ttc_cents'] ?? $existing['deduction_ttc_cents'] ?? 0);
        if ($deduction < 0) {
            throw new InvalidArgumentException('La déduction ne peut pas être négative');
        }

        if ($type === DocumentType::Quote) {
            $deduction = 0;
            $deductionLabel = '';
        } else {
            $deposit = 0;
            $deductionLabel = trim((string) ($data['deduction_label'] ?? $existing['deduction_label'] ?? ''));
            if ($deduction === 0) {
                $deductionLabel = '';
            }
        }

        return [
            'object' => (string) ($data['object'] ?? $existing['object'] ?? ''),
            'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?? null) : ($existing['notes'] ?? null),
            'valid_until' => array_key_exists('valid_until', $data)
                ? ($data['valid_until'] ?? null)
                : ($existing['valid_until'] ?? null),
            'site_address_line1' => (string) ($data['site_address_line1'] ?? $existing['site_address_line1'] ?? ''),
            'site_address_line2' => (string) ($data['site_address_line2'] ?? $existing['site_address_line2'] ?? ''),
            'site_postal_code' => (string) ($data['site_postal_code'] ?? $existing['site_postal_code'] ?? ''),
            'site_city' => (string) ($data['site_city'] ?? $existing['site_city'] ?? ''),
            'vat_rate_bp' => $vatRate,
            'deposit_ttc_cents' => $deposit,
            'deduction_label' => $deductionLabel,
            'deduction_ttc_cents' => $deduction,
        ];
    }

    private function assertWithinTotal(int $amountCents, int $totalTtcCents, DocumentType $type): void
    {
        if ($amountCents <= $totalTtcCents) {
            return;
        }
        $label = $type === DocumentType::Quote ? 'L’acompte' : 'La déduction';
        throw new InvalidArgumentException($label . ' ne peut pas dépasser le total TTC');
    }

    /** Recalcule les totaux à partir des lignes existantes (changement de TVA sans nouvelles lignes). */
    private function recomputeTotals(int $documentId, int $vatRateBp, int $limitCents, DocumentType $type): void
    {
        $lines = $this->lines($documentId);
        $payload = [];
        foreach ($lines as $line) {
            $payload[] = [
                'label' => $line['label'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
                'unit_price_ht_cents' => (int) $line['unit_price_ht_cents'],
            ];
        }
        $this->replaceLines($documentId, $payload, $vatRateBp, $limitCents, $type);
    }

    /** @param list<array> $lines */
    private function replaceLines(
        int $documentId,
        array $lines,
        int $vatRateBp,
        int $limitCents = 0,
        DocumentType $type = DocumentType::Quote,
    ): void {
        $del = $this->pdo->prepare('DELETE FROM document_lines WHERE document_id = :id');
        $del->execute(['id' => $documentId]);

        $vatExempt = (bool) $this->pdo->query('SELECT vat_exempt FROM company WHERE id = 1')->fetchColumn();
        $effectiveRate = $vatExempt ? 0 : $vatRateBp;

        $normalized = [];
        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $normalized[] = [
                'quantity' => $this->totals->parseQuantity($line['quantity'] ?? 1),
                'unit_price_ht_cents' => $this->totals->parseCents($line['unit_price_ht_cents'] ?? 0),
                'label' => $label,
                'unit' => trim((string) ($line['unit'] ?? 'u')) ?: 'u',
            ];
        }

        $computed = $this->totals->compute($normalized, $effectiveRate, $vatExempt);
        $this->assertWithinTotal($limitCents, $computed['total_ttc_cents'], $type);

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
                'vat' => $effectiveRate,
                'ht' => $computed['lines'][$i]['line_ht_cents'],
                'lvat' => 0,
            ]);
        }

        $upd = $this->pdo->prepare(
            'UPDATE documents SET total_ht_cents = :ht, total_vat_cents = :vat, total_ttc_cents = :ttc,
             vat_rate_bp = :rate
             WHERE id = :id'
        );
        $upd->execute([
            'ht' => $computed['total_ht_cents'],
            'vat' => $computed['total_vat_cents'],
            'ttc' => $computed['total_ttc_cents'],
            'rate' => $effectiveRate,
            'id' => $documentId,
        ]);
    }
}
