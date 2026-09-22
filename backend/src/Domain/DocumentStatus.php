<?php

declare(strict_types=1);

namespace Nuances\Facture\Domain;

use InvalidArgumentException;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function labelFr(DocumentType $type): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => $type === DocumentType::Invoice ? 'Envoyée' : 'Envoyé',
            self::Accepted => 'Accepté',
            self::Rejected => 'Refusé',
        };
    }

    public static function allowedFor(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::Quote => [self::Draft, self::Sent, self::Accepted, self::Rejected],
            DocumentType::Invoice => [self::Draft, self::Sent],
        };
    }

    public static function assertAllowed(DocumentType $type, self $status): void
    {
        if (!in_array($status, self::allowedFor($type), true)) {
            throw new InvalidArgumentException(
                sprintf('Statut « %s » invalide pour un %s', $status->value, $type->labelFr())
            );
        }
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
