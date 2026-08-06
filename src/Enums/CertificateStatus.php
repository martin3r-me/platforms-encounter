<?php

namespace Platform\Encounter\Enums;

enum CertificateStatus: string
{
    case Issued = 'issued';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Issued  => 'Ausgestellt',
            self::Revoked => 'Widerrufen',
        };
    }
}
