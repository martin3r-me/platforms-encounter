<?php

namespace Platform\Encounter\Enums;

/**
 * Audience — Datenschutz-Klassifizierung (Schweigepflicht).
 *
 * Steuert, was in welches Dokument fließt. Der Arbeitgeber (Employer) erhält NIE
 * medizinische Befunde — nur die Eignungs-/Vorsorge-Aussage.
 */
enum Audience: string
{
    case Patient = 'patient';    // patientenseitiges Dokument
    case Employer = 'employer';  // arbeitgeberseitig — KEINE Befunde
    case Internal = 'internal';  // nur Praxis-Team
    case Private = 'private';    // nur Ärztin/Arzt

    public function label(): string
    {
        return match ($this) {
            self::Patient  => 'Patient',
            self::Employer => 'Arbeitgeber',
            self::Internal => 'Intern',
            self::Private  => 'Vertraulich',
        };
    }
}
