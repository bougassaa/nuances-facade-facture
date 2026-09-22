<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Http;

use Nuances\Facture\Http\Request;
use Nuances\Facture\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesPathParams(): void
    {
        $router = new Router();
        $matched = null;
        $router->get('/documents/{id}/pdf', static function (Request $req, array $params) use (&$matched) {
            $matched = $params;
        });

        $req = new Request('GET', '/documents/42/pdf', [], [], [], []);
        $router->dispatch($req);

        $this->assertSame(['id' => '42'], $matched);
    }

    public function testMethodMustMatch(): void
    {
        $router = new Router();
        $called = false;
        $router->post('/clients', static function () use (&$called) {
            $called = true;
        });

        ob_start();
        $router->dispatch(new Request('GET', '/clients', [], [], [], []));
        $out = ob_get_clean();

        $this->assertFalse($called);
        $this->assertStringContainsString('introuvable', (string) $out);
    }

    public function testExactRoute(): void
    {
        $router = new Router();
        $hit = false;
        $router->get('/health', static function () use (&$hit) {
            $hit = true;
            echo '{"ok":true}';
        });

        ob_start();
        $router->dispatch(new Request('GET', '/health', [], [], [], []));
        $out = ob_get_clean();

        $this->assertTrue($hit);
        $this->assertSame('{"ok":true}', $out);
    }
}
