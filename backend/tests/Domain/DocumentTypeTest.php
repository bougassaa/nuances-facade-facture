<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Domain;

use Nuances\Facture\Domain\DocumentType;
use PHPUnit\Framework\TestCase;

final class DocumentTypeTest extends TestCase
{
    public function testPrefixesAndLabels(): void
    {
        $this->assertSame('DEV', DocumentType::Quote->numberPrefix());
        $this->assertSame('FAC', DocumentType::Invoice->numberPrefix());
        $this->assertSame('Devis', DocumentType::Quote->labelFr());
        $this->assertSame('Facture', DocumentType::Invoice->labelFr());
    }

    public function testFromString(): void
    {
        $this->assertSame(DocumentType::Quote, DocumentType::from('quote'));
        $this->assertSame(DocumentType::Invoice, DocumentType::from('invoice'));
    }
}
