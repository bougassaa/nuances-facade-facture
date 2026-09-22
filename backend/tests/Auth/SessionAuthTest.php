<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Auth;

use Nuances\Facture\Auth\SessionAuth;
use Nuances\Facture\Tests\IntegrationTestCase;

final class SessionAuthTest extends IntegrationTestCase
{
    private SessionAuth $auth;

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        $this->pdo()->exec('DELETE FROM users');
        $this->auth = new SessionAuth($this->pdo());
    }

    public function testCreateUserAndLogin(): void
    {
        $this->assertSame(0, $this->auth->userCount());
        $id = $this->auth->createUser('test@example.com', 'password123');
        $this->assertSame(1, $this->auth->userCount());

        $user = $this->auth->findByEmail('test@example.com');
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('password123', (string) $user['password_hash']));

        $this->auth->login($id);
        $this->assertSame($id, $this->auth->userId());
        $this->assertNotEmpty($this->auth->ensureCsrf());
    }

    public function testUnknownEmailReturnsNull(): void
    {
        $this->assertNull($this->auth->findByEmail('nobody@example.com'));
    }

    public function testLogoutClearsUserId(): void
    {
        $id = $this->auth->createUser('out@example.com', 'password123');
        $this->auth->login($id);
        $this->assertSame($id, $this->auth->userId());
        $_SESSION = [];
        $this->assertNull($this->auth->userId());
    }
}
