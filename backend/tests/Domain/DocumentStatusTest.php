<?php

declare(strict_types=1);

namespace Nuances\Facture\Tests\Domain;

use InvalidArgumentException;
use Nuances\Facture\Domain\DocumentStatus;
use Nuances\Facture\Domain\DocumentType;
use PHPUnit\Framework\TestCase;

final class DocumentStatusTest extends TestCase
{
    public function testQuoteAllowsAcceptedAndRejected(): void
    {
        $allowed = DocumentStatus::allowedFor(DocumentType::Quote);
        $this->assertContains(DocumentStatus::Draft, $allowed);
        $this->assertContains(DocumentStatus::Sent, $allowed);
        $this->assertContains(DocumentStatus::Accepted, $allowed);
        $this->assertContains(DocumentStatus::Rejected, $allowed);
    }

    public function testInvoiceDoesNotAllowAcceptedOrRejected(): void
    {
        $allowed = DocumentStatus::allowedFor(DocumentType::Invoice);
        $this->assertSame(
            [DocumentStatus::Draft, DocumentStatus::Sent],
            $allowed
        );
    }

    public function testAssertAllowedRejectsPaidLikeStatusOnInvoice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DocumentStatus::assertAllowed(DocumentType::Invoice, DocumentStatus::Accepted);
    }

    public function testOnlyDraftIsEditable(): void
    {
        $this->assertTrue(DocumentStatus::Draft->isEditable());
        $this->assertFalse(DocumentStatus::Sent->isEditable());
        $this->assertFalse(DocumentStatus::Accepted->isEditable());
        $this->assertFalse(DocumentStatus::Rejected->isEditable());
    }

    public function testLabelsFr(): void
    {
        $this->assertSame('Brouillon', DocumentStatus::Draft->labelFr(DocumentType::Quote));
        $this->assertSame('Envoyé', DocumentStatus::Sent->labelFr(DocumentType::Quote));
        $this->assertSame('Envoyée', DocumentStatus::Sent->labelFr(DocumentType::Invoice));
        $this->assertSame('Accepté', DocumentStatus::Accepted->labelFr(DocumentType::Quote));
        $this->assertSame('Refusé', DocumentStatus::Rejected->labelFr(DocumentType::Quote));
    }

    public function testNoPaidStatusExists(): void
    {
        $values = array_map(
            static fn (DocumentStatus $s) => $s->value,
            DocumentStatus::cases()
        );
        $this->assertNotContains('paid', $values);
        $this->assertNotContains('paye', $values);
        $this->assertNotContains('payé', $values);
    }
}
