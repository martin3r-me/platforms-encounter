<?php

namespace Platform\Encounter\Enums;

/**
 * LocationType — Ort der Terminerbringung. In der Arbeitsmedizin operativ zentral
 * (Praxis vs. Außendienst planen sich völlig anders).
 */
enum LocationType: string
{
    case Practice = 'practice'; // Im Haus (Praxis-Standort)
    case Company  = 'company';  // Im Betrieb (beim Arbeitgeber vor Ort)
    case Home     = 'home';     // Hausbesuch
    case Remote   = 'remote';   // Telemedizin / Video

    public function label(): string
    {
        return match ($this) {
            self::Practice => 'Im Haus',
            self::Company  => 'Im Betrieb',
            self::Home     => 'Hausbesuch',
            self::Remote   => 'Telemedizin',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Practice => 'heroicon-o-building-office',
            self::Company  => 'heroicon-o-building-office-2',
            self::Home     => 'heroicon-o-home',
            self::Remote   => 'heroicon-o-video-camera',
        };
    }

    /** Farbe (hex) für Badge/Punkt. */
    public function color(): string
    {
        return match ($this) {
            self::Practice => '#2563eb', // blau
            self::Company  => '#d97706', // amber
            self::Home     => '#16a34a', // grün
            self::Remote   => '#7c3aed', // violett
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return [
            self::Practice->value => self::Practice->label(),
            self::Company->value  => self::Company->label(),
            self::Home->value     => self::Home->label(),
            self::Remote->value   => self::Remote->label(),
        ];
    }
}
