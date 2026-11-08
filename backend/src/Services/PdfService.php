<?php

declare(strict_types=1);

namespace Nuances\Facture\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;

final class PdfService
{
    private readonly string $signaturesDir;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $templatesDir,
        private readonly string $logosDir,
        ?string $signaturesDir = null,
    ) {
        $this->signaturesDir = $signaturesDir ?? dirname($this->logosDir) . '/signatures';
    }

    public function renderDocument(int $documentId): string
    {
        $doc = $this->loadDocument($documentId);
        $company = $this->loadCompany();
        $html = $this->buildHtml($documentId);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
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

        $logoDataUri = $this->imageDataUri($this->logosDir, $company['logo_path'] ?? null);
        $signatureDataUri = $this->imageDataUri($this->signaturesDir, $company['signature_path'] ?? null);

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

    /**
     * Textes du pied de page (coordonnées, puis mentions légales), avant retour à la ligne.
     *
     * @return array{0: string, 1: string}
     */
    public static function footerTexts(array $company): array
    {
        $address = array_values(array_filter([
            trim((string) ($company['address_line1'] ?? '')),
            trim((string) ($company['address_line2'] ?? '')),
            trim(($company['postal_code'] ?? '') . ' ' . ($company['city'] ?? '')),
        ], static fn (string $part): bool => $part !== ''));
        $address[] = 'France';

        $contact = [trim((string) ($company['name'] ?? '')) . ' — ' . implode(', ', $address)];
        if (!empty($company['phone'])) {
            $contact[] = 'Téléphone : ' . $company['phone'];
        }
        if (!empty($company['email'])) {
            $contact[] = 'e-mail : ' . $company['email'];
        }

        $legal = [trim((string) ($company['legal_form'] ?? 'EI')) ?: 'EI'];
        if (!empty($company['siret'])) {
            $legal[] = 'SIRET : ' . $company['siret'];
        }
        if (!empty($company['legal_decennale'])) {
            $legal[] = 'Assurance : ' . preg_replace('/\s+/', ' ', trim((string) $company['legal_decennale']));
        }
        if (!empty($company['iban'])) {
            $legal[] = 'IBAN : ' . $company['iban'];
        }

        return [implode(' — ', $contact), implode(' — ', $legal)];
    }

    /** Pied de page centré sous un filet, pagination à droite ; numéro en haut à droite dès la page 2. */
    private function addFooter(Dompdf $dompdf, array $company, string $number): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('Helvetica');
        $size = 7.5;
        $maxWidth = $canvas->get_width() - 2 * 80;

        $lines = [];
        foreach (self::footerTexts($company) as $text) {
            foreach ($this->wrap($text, $maxWidth, static fn (string $t): float => $metrics->getTextWidth($t, $font, $size)) as $line) {
                $lines[] = [$line, $metrics->getTextWidth($line, $font, $size)];
            }
        }
        $numberWidth = $number !== '' ? $metrics->getTextWidth($number, $font, 9.0) : 0.0;

        $canvas->page_script(static function (
            int $pageNumber,
            int $pageCount,
            $pdf,
            $fontMetrics
        ) use ($font, $size, $lines, $number, $numberWidth): void {
            $w = $pdf->get_width();
            $h = $pdf->get_height();
            $grey = [0.33, 0.33, 0.33];
            $top = $h - 56;

            $pdf->line(18, $top, $w - 18, $top, [0.75, 0.75, 0.75], 0.6);

            $page = 'Page ' . $pageNumber . ' / ' . $pageCount;
            $pageWidth = $fontMetrics->getTextWidth($page, $font, $size + 0.5);
            $pdf->text($w - 18 - $pageWidth, $top + 7, $page, $font, $size + 0.5, $grey);

            foreach ($lines as $i => [$text, $width]) {
                $pdf->text(($w - $width) / 2, $top + 7 + $i * 10.5, $text, $font, $size, $grey);
            }

            if ($number !== '' && $pageNumber > 1) {
                $pdf->text($w - 18 - $numberWidth, 12, $number, $font, 9.0, $grey);
            }
        });
    }

    /**
     * Retour à la ligne mot à mot selon une largeur en points.
     *
     * @param callable(string): float $measure
     * @return list<string>
     */
    private function wrap(string $text, float $maxWidth, callable $measure): array
    {
        $lines = [];
        $current = '';
        foreach (preg_split('/ /', $text) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && $measure($candidate) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines;
    }

    private function imageDataUri(string $dir, mixed $relative): ?string
    {
        if (!is_string($relative) || $relative === '') {
            return null;
        }
        $full = $dir . '/' . basename($relative);
        if (!is_file($full)) {
            return null;
        }
        $mime = mime_content_type($full) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($full));
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
