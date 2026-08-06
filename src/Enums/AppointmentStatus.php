<?php

namespace Platform\Encounter\Enums;

enum AppointmentStatus: string
{
    case Planned = 'planned';
    case Attended = 'attended';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Planned     => 'Geplant',
            self::Attended    => 'Wahrgenommen',
            self::Cancelled   => 'Abgesagt',
            self::Rescheduled => 'Verschoben',
        };
    }
}
