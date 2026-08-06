<?php

namespace Platform\Encounter\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Boolean = 'boolean';
    case Date = 'date';
    case Select = 'select';

    public function label(): string
    {
        return match ($this) {
            self::Text     => 'Text',
            self::Textarea => 'Mehrzeilig',
            self::Number   => 'Zahl',
            self::Boolean  => 'Ja/Nein',
            self::Date     => 'Datum',
            self::Select   => 'Auswahl',
        };
    }
}
