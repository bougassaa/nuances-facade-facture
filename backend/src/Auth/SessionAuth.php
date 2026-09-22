<?php

declare(strict_types=1);

namespace Nuances\Facture\Auth;

use Nuances\Facture\Http\Request;
use Nuances\Facture\Http\Response;
use PDO;

final class SessionAuth
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public function requireCsrf(Request $request): bool
    {
        $token = $request->header('x-csrf-token') ?? '';
        $session = $_SESSION['csrf_token'] ?? '';
        if ($token === '' || $session === '' || !hash_equals((string) $session, $token)) {
            Response::error('Jeton CSRF invalide', 403);
            return false;
        }
        return true;
    }

    public function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function requireUser(): ?int
    {
        $id = $this->userId();
        if ($id === null) {
            Response::error('Non authentifié', 401);
            return null;
        }
        return $id;
    }

    public function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $this->ensureCsrf();
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public function userCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createUser(string $email, string $password): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :hash)');
        $stmt->execute(['email' => $email, 'hash' => $hash]);
        return (int) $this->pdo->lastInsertId();
    }
}
