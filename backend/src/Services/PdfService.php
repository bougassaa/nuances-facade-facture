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

        $document = $doc;
        // $company, $client, $lines, $logoDataUri already in scope for the template
        ob_start();
        include $this->templatesDir . '/document.php';
        $html = (string) ob_get_clean();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot([$this->templatesDir, $this->logosDir]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
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
