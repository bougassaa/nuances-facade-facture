<?php

declare(strict_types=1);

namespace Nuances\Facture\Repositories;

use PDO;
use RuntimeException;

final class ClientRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(?string $q = null): array
    {
        if ($q !== null && $q !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM clients WHERE name LIKE :q1 OR email LIKE :q2 OR city LIKE :q3
                 ORDER BY name ASC LIMIT 100'
            );
            $like = '%' . $q . '%';
            $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like]);
            return $stmt->fetchAll();
        }
        return $this->pdo->query('SELECT * FROM clients ORDER BY name ASC LIMIT 200')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO clients (name, address_line1, address_line2, postal_code, city, email, phone, vat_number, notes)
             VALUES (:name, :a1, :a2, :cp, :city, :email, :phone, :vat, :notes)'
        );
        $stmt->execute($this->bind($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if ($this->find($id) === null) {
            throw new RuntimeException('Client introuvable');
        }
        $params = $this->bind($data);
        $params['id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE clients SET name = :name, address_line1 = :a1, address_line2 = :a2,
             postal_code = :cp, city = :city, email = :email, phone = :phone,
             vat_number = :vat, notes = :notes WHERE id = :id'
        );
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $check = $this->pdo->prepare('SELECT COUNT(*) FROM documents WHERE client_id = :id');
        $check->execute(['id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            throw new RuntimeException('Impossible de supprimer : des documents sont liés à ce client');
        }
        $stmt = $this->pdo->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function bind(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Le nom du client est obligatoire');
        }
        return [
            'name' => $name,
            'a1' => (string) ($data['address_line1'] ?? ''),
            'a2' => (string) ($data['address_line2'] ?? ''),
            'cp' => (string) ($data['postal_code'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'vat' => (string) ($data['vat_number'] ?? ''),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
