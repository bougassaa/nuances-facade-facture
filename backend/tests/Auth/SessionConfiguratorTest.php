<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Auth;

use Nuances\Facture\Auth\SessionConfigurator;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SessionConfiguratorTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSessionPersistsOneYearInDedicatedDirectory(): void
    {
        $root = dirname(__DIR__, 2);
        $expectedPath = $root . '/storage/sessions';

        SessionConfigurator::start($root, 'NFSESSID_TEST', false);

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame('NFSESSID_TEST', session_name());
        $this->assertSame((string) SessionConfigurator::LIFETIME_SECONDS, ini_get('session.gc_maxlifetime'));
        $this->assertSame($expectedPath, session_save_path());

        $params = session_get_cookie_params();
        $this->assertSame(SessionConfigurator::LIFETIME_SECONDS, $params['lifetime']);
        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite'] ?? '');
        $this->assertDirectoryExists($expectedPath);
    }

    public function testLifetimeConstantIsOneYear(): void
    {
        $this->assertSame(31_536_000, SessionConfigurator::LIFETIME_SECONDS);
    }
}
