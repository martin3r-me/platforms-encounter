<?php

namespace Platform\Encounter\Enums;

enum Participation: string
{
    case Pending = 'pending';
    case Attended = 'attended';
    case NoShow = 'no_show';
    case Aborted = 'aborted';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Ausstehend',
            self::Attended => 'Teilgenommen',
            self::NoShow   => 'Nicht erschienen',
            self::Aborted  => 'Abgebrochen',
        };
    }
}
