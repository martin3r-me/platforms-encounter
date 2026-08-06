<?php

namespace Platform\Encounter\Contracts;

/**
 * JournalEntryProvider — ein Fachmodul liefert datierte Einträge in den Verlauf (die Akte)
 * eines Patienten bei. encounter (Termine), occupational (Vorsorge/Beschäftigung), später
 * lab (Werte) docken additiv an. Die Akte merged + sortiert (neueste zuerst).
 *
 * Eintrag-Form:
 *  [
 *    'date'     => Carbon|\DateTimeInterface,     // Sortier-/Anzeige-Zeitpunkt
 *    'anchor'   => 'appt-12',                     // eindeutig, für Sprung-Marken
 *    'type'     => 'appointment'|'provision'|…,
 *    'icon'     => 'heroicon-o-…',
 *    'title'    => '…',
 *    'subtitle' => '…'|null,
 *    'badge'    => ['label'=>'…','variant'=>'default'|'danger'|'success']|null,
 *    'lines'    => ['Befund: …', 'Leistung: …', …],
 *    'url'      => '…'|null,                       // zum Öffnen/Bearbeiten
 *  ]
 */
interface JournalEntryProvider
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function entriesFor(int $patientId, int $teamId): array;
}
