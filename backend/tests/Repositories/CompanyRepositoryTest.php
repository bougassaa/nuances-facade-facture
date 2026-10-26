<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Repositories;

use Nuances\Facture\Repositories\CompanyRepository;
use Nuances\Facture\Tests\IntegrationTestCase;
use RuntimeException;

final class CompanyRepositoryTest extends IntegrationTestCase
{
    public function testUpdateAndGet(): void
    {
        $logos = dirname(__DIR__, 2) . '/storage/logos';
        $repo = new CompanyRepository($this->pdo(), $logos);

        $repo->update([
            'name' => 'Nuances Test',
            'city' => 'Lyon',
            'siret' => '123',
            'website' => 'www.nuances-facade.fr',
            'legal_form' => 'EI',
            'payment_terms' => 'Paiement à réception.',
            'vat_exempt' => true,
            'legal_decennale' => 'Assurance X',
        ]);

        $company = $repo->get();
        $this->assertSame('Nuances Test', $company['name']);
        $this->assertSame('Lyon', $company['city']);
        $this->assertSame('www.nuances-facade.fr', $company['website']);
        $this->assertSame('EI', $company['legal_form']);
        $this->assertSame('Paiement à réception.', $company['payment_terms']);
        $this->assertTrue((bool) $company['vat_exempt']);
        $this->assertSame('Assurance X', $company['legal_decennale']);
        $this->assertArrayHasKey('vat_rates', $company);
        $this->assertNotEmpty($company['vat_rates']);
        $this->assertArrayNotHasKey('logo_path', $company);
        $this->assertArrayNotHasKey('signature_path', $company);
        $this->assertArrayHasKey('has_logo', $company);
        $this->assertArrayHasKey('has_signature', $company);
        $this->assertArrayHasKey('counters', $company);
        $this->assertArrayHasKey('year', $company['counters']);
    }

    public function testSaveSignatureCropsBlankMarginsAndDeleteRemovesIt(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD absent');
        }
        $dir = sys_get_temp_dir() . '/nf-sig-repo-' . uniqid('', true);
        $src = $dir . '-src.png';
        $img = imagecreatetruecolor(80, 40);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, 79, 39, $white);
        imagefilledrectangle($img, 40, 18, 44, 22, $black);
        imagepng($img, $src);
        imagedestroy($img);

        $repo = new CompanyRepository($this->pdo(), dirname(__DIR__, 2) . '/storage/logos', null, $dir);
        $repo->saveSignature([
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $src,
        ]);

        $company = $repo->get();
        $this->assertTrue($company['has_signature']);
        $this->assertArrayNotHasKey('signature_path', $company);
        $stored = $repo->signatureAbsolutePath();
        $this->assertNotNull($stored);
        $size = getimagesize((string) $stored);
        $this->assertNotFalse($size);
        $this->assertLessThan(80, $size[0]);

        $repo->deleteSignature();
        $this->assertFalse($repo->get()['has_signature']);
        $this->assertNull($repo->signatureAbsolutePath());
        @unlink($src);
    }

    public function testRejectsSignatureThatIsNotAnImage(): void
    {
        $dir = sys_get_temp_dir() . '/nf-sig-bad-' . uniqid('', true);
        mkdir($dir);
        $src = $dir . '/note.txt';
        file_put_contents($src, 'pas une image');
        $repo = new CompanyRepository($this->pdo(), dirname(__DIR__, 2) . '/storage/logos', null, $dir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format de signature non supporté');
        $repo->saveSignature([
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $src,
        ]);
    }
}
