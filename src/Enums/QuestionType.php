<?php

namespace Platform\Encounter\Enums;

/**
 * QuestionType — Antworttyp einer Anamnese-Frage.
 */
enum QuestionType: string
{
    case YesNo  = 'yes_no'; // Ja/Nein
    case Text   = 'text';   // Freitext
    case Scale  = 'scale';  // Skala (options = Werte)
    case Choice = 'choice'; // Auswahl (options = Optionen)

    public function label(): string
    {
        return match ($this) {
            self::YesNo  => 'Ja/Nein',
            self::Text   => 'Freitext',
            self::Scale  => 'Skala',
            self::Choice => 'Auswahl',
        };
    }

    /** @return array<string,string> value => label */
    public static function options(): array
    {
        return [
            self::YesNo->value  => self::YesNo->label(),
            self::Text->value   => self::Text->label(),
            self::Scale->value  => self::Scale->label(),
            self::Choice->value => self::Choice->label(),
        ];
    }
}
