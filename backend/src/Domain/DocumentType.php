<?php

declare(strict_types=1);

namespace Nuances\Facture\Domain;

enum DocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Quote => 'DEV',
            self::Invoice => 'FAC',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Quote => 'Devis',
            self::Invoice => 'Facture',
        };
    }
}
