<?php

namespace Platform\Encounter\Contracts;

/**
 * LetterheadProvider — liefert den Briefkopf (Praxis-Identität + ausstellender Arzt)
 * für ein Dokument (Bescheinigung). Inversion: encounter kennt keine Praxis-Details;
 * das practice-Modul registriert einen Provider. Höchste Priorität, die Daten liefert, gewinnt.
 */
interface LetterheadProvider
{
    /** Höhere Zahl = bevorzugt (practice-Modul schlägt den encounter-internen Fallback). */
    public function letterheadPriority(): int;

    /**
     * @param  array<string,mixed>  $context  z.B. ['standort_entity_id'=>…, 'doctor_entity_id'=>…]
     * @return array<string,mixed>|null       null, wenn dieser Provider nichts liefern kann
     */
    public function letterheadFor(int $teamId, array $context = []): ?array;
}
