<?php

declare(strict_types=1);

namespace Nuances\Facture\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;

final class PdfService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $templatesDir,
        private readonly string $logosDir,
    ) {
    }

    public function renderDocument(int $documentId): string
    {
        $doc = $this->loadDocument($documentId);
        $company = $this->loadCompany();
        $html = $this->buildHtml($documentId);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot([$this->templatesDir, $this->logosDir]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->addFooter($dompdf, $company, (string) ($doc['number'] ?? ''));

        return $dompdf->output();
    }

    /** Rendu HTML du template (tests / inspection). */
    public function renderHtml(int $documentId): string
    {
        return $this->buildHtml($documentId);
    }

    private function buildHtml(int $documentId): string
    {
        $doc = $this->loadDocument($documentId);
        $company = $this->loadCompany();
        $client = $this->loadClient((int) $doc['client_id']);
        $lines = $this->loadLines($documentId);

        $logoDataUri = null;
        if (!empty($company['logo_path'])) {
            $full = $this->logosDir . '/' . basename((string) $company['logo_path']);
            if (is_file($full)) {
                $mime = mime_content_type($full) ?: 'image/png';
                $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($full));
            }
        }

        $calculator = new TotalsCalculator();
        $depositAmountCents = (int) ($doc['deposit_ttc_cents'] ?? 0);
        $remainingDueCents = $calculator->remainingDueCents(
            (int) $doc['total_ttc_cents'],
            (int) ($doc['deduction_ttc_cents'] ?? 0)
        );

        $document = $doc;
        ob_start();
        include $this->templatesDir . '/document.php';
        return (string) ob_get_clean();
    }

    private function addFooter(Dompdf $dompdf, array $company, string $number): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $legalForm = trim((string) ($company['legal_form'] ?? 'EI')) ?: 'EI';
        $parts = [$legalForm];
        if (!empty($company['siret'])) {
            $parts[] = 'SIRET : ' . $company['siret'];
        }
        if (!empty($company['legal_decennale'])) {
            $assurance = preg_replace('/\s+/', ' ', trim((string) $company['legal_decennale']));
            $parts[] = 'Assurance : ' . $assurance;
        }
        if (!empty($company['iban'])) {
            $parts[] = 'IBAN : ' . $company['iban'];
        }
        $legalLine = $this->truncate(implode(' — ', $parts), 118);

        $addressBits = array_filter([
            $company['address_line1'] ?? '',
            trim(($company['postal_code'] ?? '') . ' ' . ($company['city'] ?? '')),
            'France',
        ]);
        $contactBits = [];
        if (!empty($company['phone'])) {
            $contactBits[] = 'Téléphone : ' . $company['phone'];
        }
        if (!empty($company['email'])) {
            $contactBits[] = 'e-mail : ' . $company['email'];
        }
        $headerLine = trim((string) ($company['name'] ?? '')) . ' — '
            . implode(', ', $addressBits);
        if ($contactBits !== []) {
            $headerLine .= ' — ' . implode(' — ', $contactBits);
        }
        $headerLine = $this->truncate($headerLine, 118);

        $canvas->page_script(static function (
            int $pageNumber,
            int $pageCount,
            $pdf,
            $fontMetrics
        ) use ($font, $headerLine, $legalLine, $number): void {
            $w = $pdf->get_width();
            $h = $pdf->get_height();
            $size = 7.0;
            $y = $h - 48;

            $pdf->text(42, $y, 'Page ' . $pageNumber . ' / ' . $pageCount, $font, $size);
            if ($number !== '' && $pageNumber > 1) {
                $pdf->text($w - 120, 28, $number, $font, $size);
            }
            $pdf->text(42, $y + 12, $headerLine, $font, $size);
            $pdf->text(42, $y + 22, $legalLine, $font, $size);
        });
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 1) . '…';
    }

    private function loadDocument(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new \RuntimeException('Document introuvable');
        }
        return $row;
    }

    private function loadCompany(): array
    {
        $row = $this->pdo->query('SELECT * FROM company WHERE id = 1')->fetch();
        return $row ?: [];
    }

    private function loadClient(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    /** @return list<array> */
    private function loadLines(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM document_lines WHERE document_id = :id ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['id' => $documentId]);
        return $stmt->fetchAll();
    }
}
