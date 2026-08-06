<?php

namespace Platform\Encounter\Enums;

enum DeliveryChannel: string
{
    case Print = 'print';
    case Email = 'email';
    case Postal = 'postal';

    public function label(): string
    {
        return match ($this) {
            self::Print  => 'Druck',
            self::Email  => 'E-Mail',
            self::Postal => 'Post',
        };
    }
}
