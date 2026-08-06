<?php

namespace Platform\Encounter\Enums;

enum ExaminationType: string
{
    case Initial = 'initial';    // Erstuntersuchung
    case FollowUp = 'follow_up'; // Nachuntersuchung

    public function label(): string
    {
        return match ($this) {
            self::Initial  => 'Erstuntersuchung',
            self::FollowUp => 'Nachuntersuchung',
        };
    }
}
