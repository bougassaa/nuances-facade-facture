<?php

declare(strict_types=1);

namespace Nuances\Facture\Repositories;

use PDO;
use RuntimeException;

final class CompanyRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $logosDir,
    ) {
    }

    public function get(): array
    {
        $row = $this->pdo->query('SELECT * FROM company WHERE id = 1')->fetch();
        if ($row === false) {
            $this->pdo->exec("INSERT INTO company (id, name) VALUES (1, '')");
            $row = $this->pdo->query('SELECT * FROM company WHERE id = 1')->fetch();
        }
        $row['vat_rates'] = $this->pdo->query('SELECT * FROM vat_rates ORDER BY rate_bp DESC')->fetchAll();
        unset($row['logo_path']);
        $row['has_logo'] = $this->logoExists();
        return $row;
    }

    public function update(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE company SET
              name = :name, address_line1 = :a1, address_line2 = :a2,
              postal_code = :cp, city = :city, phone = :phone, email = :email,
              siret = :siret, vat_number = :vat, iban = :iban, bic = :bic,
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
            'exempt' => !empty($data['vat_exempt']) ? 1 : 0,
            'dec' => $data['legal_decennale'] ?? null,
            'late' => $data['legal_late_penalties'] ?? null,
            'fee' => $data['legal_recovery_fee'] ?? null,
            'valid' => $data['legal_quote_validity'] ?? null,
            'extra' => $data['legal_extra'] ?? null,
        ]);
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
}
