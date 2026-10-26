<?php

declare(strict_types=1);

namespace Nuances\Facture\Repositories;

use Nuances\Facture\Domain\DocumentType;
use Nuances\Facture\Services\DocumentNumberService;
use PDO;
use RuntimeException;

final class CompanyRepository
{
    private readonly string $signaturesDir;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $logosDir,
        private readonly ?DocumentNumberService $numbers = null,
        ?string $signaturesDir = null,
    ) {
        $this->signaturesDir = $signaturesDir ?? dirname($this->logosDir) . '/signatures';
    }

    public function get(): array
    {
        $row = $this->pdo->query('SELECT * FROM company WHERE id = 1')->fetch();
        if ($row === false) {
            $this->pdo->exec("INSERT INTO company (id, name) VALUES (1, '')");
            $row = $this->pdo->query('SELECT * FROM company WHERE id = 1')->fetch();
        }
        $row['vat_rates'] = $this->pdo->query('SELECT * FROM vat_rates ORDER BY rate_bp DESC')->fetchAll();
        unset($row['logo_path'], $row['signature_path']);
        $row['has_logo'] = $this->logoExists();
        $row['has_signature'] = $this->signatureExists();
        $row['counters'] = $this->currentCounters();
        return $row;
    }

    public function update(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE company SET
              name = :name, address_line1 = :a1, address_line2 = :a2,
              postal_code = :cp, city = :city, phone = :phone, email = :email,
              siret = :siret, vat_number = :vat, iban = :iban, bic = :bic,
              website = :website, legal_form = :legal_form, payment_terms = :payment_terms,
              vat_exempt = :exempt,
              legal_decennale = :dec, legal_late_penalties = :late,
              legal_recovery_fee = :fee, legal_quote_validity = :valid, legal_extra = :extra
             WHERE id = 1'
        );
        $stmt->execute([
            'name' => (string) ($data['name'] ?? ''),
            'a1' => (string) ($data['address_line1'] ?? ''),
            'a2' => (string) ($data['address_line2'] ?? ''),
            'cp' => (string) ($data['postal_code'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'siret' => (string) ($data['siret'] ?? ''),
            'vat' => (string) ($data['vat_number'] ?? ''),
            'iban' => (string) ($data['iban'] ?? ''),
            'bic' => (string) ($data['bic'] ?? ''),
            'website' => (string) ($data['website'] ?? ''),
            'legal_form' => trim((string) ($data['legal_form'] ?? 'EI')) ?: 'EI',
            'payment_terms' => $data['payment_terms'] ?? null,
            'exempt' => !empty($data['vat_exempt']) ? 1 : 0,
            'dec' => $data['legal_decennale'] ?? null,
            'late' => $data['legal_late_penalties'] ?? null,
            'fee' => $data['legal_recovery_fee'] ?? null,
            'valid' => $data['legal_quote_validity'] ?? null,
            'extra' => $data['legal_extra'] ?? null,
        ]);

        if ($this->numbers !== null && isset($data['last_quote_number'], $data['last_invoice_number'])) {
            $year = (int) date('Y');
            $this->numbers->setLastNumber(DocumentType::Quote, $year, (int) $data['last_quote_number']);
            $this->numbers->setLastNumber(DocumentType::Invoice, $year, (int) $data['last_invoice_number']);
        }
    }

    public function saveLogo(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Échec de l’upload du logo');
        }
        $tmp = (string) $file['tmp_name'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($map[$mime])) {
            throw new RuntimeException('Format logo non supporté (PNG, JPEG, WebP, GIF)');
        }
        if (!is_dir($this->logosDir) && !mkdir($this->logosDir, 0755, true) && !is_dir($this->logosDir)) {
            throw new RuntimeException('Impossible de créer le dossier logos');
        }
        $name = 'logo.' . $map[$mime];
        $dest = $this->logosDir . '/' . $name;
        foreach (glob($this->logosDir . '/logo.*') ?: [] as $old) {
            @unlink($old);
        }
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Impossible d’enregistrer le logo');
        }
        $stmt = $this->pdo->prepare('UPDATE company SET logo_path = :p WHERE id = 1');
        $stmt->execute(['p' => $name]);
    }

    public function saveSignature(array $file): void
    {
        $name = $this->storeImage(
            $file,
            $this->signaturesDir,
            'signature',
            'Échec de l’upload de la signature',
            'Format de signature non supporté (PNG, JPEG, WebP, GIF)',
            'Impossible de créer le dossier des signatures',
            'Impossible d’enregistrer la signature',
        );
        $full = $this->signaturesDir . '/' . $name;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($full) ?: '';
        $this->prepareSignatureFile($full, $mime);
        $stmt = $this->pdo->prepare('UPDATE company SET signature_path = :p WHERE id = 1');
        $stmt->execute(['p' => $name]);
    }

    public function deleteSignature(): void
    {
        foreach (glob($this->signaturesDir . '/signature.*') ?: [] as $old) {
            @unlink($old);
        }
        $this->pdo->exec('UPDATE company SET signature_path = NULL WHERE id = 1');
    }

    public function signatureAbsolutePath(): ?string
    {
        $path = $this->pdo->query('SELECT signature_path FROM company WHERE id = 1')->fetchColumn();
        if (!$path) {
            return null;
        }
        $full = $this->signaturesDir . '/' . basename((string) $path);
        return is_file($full) ? $full : null;
    }

    public function logoAbsolutePath(): ?string
    {
        $path = $this->pdo->query('SELECT logo_path FROM company WHERE id = 1')->fetchColumn();
        if (!$path) {
            return null;
        }
        $full = $this->logosDir . '/' . basename((string) $path);
        return is_file($full) ? $full : null;
    }

    private function logoExists(): bool
    {
        return $this->logoAbsolutePath() !== null;
    }

    private function signatureExists(): bool
    {
        return $this->signatureAbsolutePath() !== null;
    }

    /**
     * @param array{error?: int, tmp_name?: string} $file
     */
    private function storeImage(
        array $file,
        string $dir,
        string $basename,
        string $uploadError,
        string $formatError,
        string $dirError,
        string $saveError,
    ): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($uploadError);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $tmp !== '' && is_file($tmp) ? ($finfo->file($tmp) ?: '') : '';
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($map[$mime])) {
            throw new RuntimeException($formatError);
        }
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException($dirError);
        }
        $name = $basename . '.' . $map[$mime];
        $dest = $dir . '/' . $name;
        foreach (glob($dir . '/' . $basename . '.*') ?: [] as $old) {
            @unlink($old);
        }
        $stored = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : copy($tmp, $dest);
        if (!$stored) {
            throw new RuntimeException($saveError);
        }
        return $name;
    }

    /** Réduit et recadre le fond blanc pour que le trait remplisse le cadre du devis. */
    private function prepareSignatureFile(string $path, string $mime): void
    {
        if (!extension_loaded('gd') || !is_file($path)) {
            return;
        }
        $img = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($img === false) {
            return;
        }
        $img = $this->downscaleGd($img, 1400);
        $cropped = $this->cropNearWhite($img);
        if ($cropped !== $img) {
            imagedestroy($img);
            $img = $cropped;
        }
        match ($mime) {
            'image/png' => imagepng($img, $path),
            'image/jpeg' => imagejpeg($img, $path, 90),
            'image/gif' => imagegif($img, $path),
            'image/webp' => function_exists('imagewebp') ? imagewebp($img, $path, 90) : null,
            default => null,
        };
        imagedestroy($img);
    }

    /** @param \GdImage $img */
    private function downscaleGd(\GdImage $img, int $maxSide): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $long = max($w, $h);
        if ($long <= $maxSide || $long === 0) {
            return $img;
        }
        $scale = $maxSide / $long;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $next = imagecreatetruecolor($nw, $nh);
        imagealphablending($next, false);
        imagesavealpha($next, true);
        $transparent = imagecolorallocatealpha($next, 0, 0, 0, 127);
        imagefilledrectangle($next, 0, 0, $nw, $nh, $transparent);
        imagecopyresampled($next, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        return $next;
    }

    /** @param \GdImage $img */
    private function cropNearWhite(\GdImage $img): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha > 100) {
                    continue;
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r >= 235 && $g >= 235 && $b >= 235) {
                    continue;
                }
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < $minX || $maxY < $minY) {
            return $img;
        }
        $pad = 16;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($w - 1, $maxX + $pad);
        $maxY = min($h - 1, $maxY + $pad);
        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;
        if ($cw >= $w - 1 && $ch >= $h - 1) {
            return $img;
        }
        $next = imagecrop($img, ['x' => $minX, 'y' => $minY, 'width' => $cw, 'height' => $ch]);
        return $next === false ? $img : $next;
    }

    /** @return array{year: int, quote: int, invoice: int} */
    private function currentCounters(): array
    {
        $year = (int) date('Y');
        $quote = 0;
        $invoice = 0;
        $stmt = $this->pdo->prepare('SELECT doc_type, last_number FROM counters WHERE year = :year');
        $stmt->execute(['year' => $year]);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['doc_type'] === DocumentType::Quote->value) {
                $quote = (int) $row['last_number'];
            } elseif ($row['doc_type'] === DocumentType::Invoice->value) {
                $invoice = (int) $row['last_number'];
            }
        }
        return ['year' => $year, 'quote' => $quote, 'invoice' => $invoice];
    }
}
