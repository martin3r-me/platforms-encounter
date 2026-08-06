<?php

namespace Platform\Encounter\Journal;

use Illuminate\Support\Str;
use Platform\Encounter\Contracts\JournalEntryProvider;
use Platform\Encounter\Models\Appointment;

/**
 * Liefert die Termine eines Patienten als Verlauf-Einträge (mit Befund-Kurzfassung,
 * Leistungen, Bescheinigungen). Kern der Akte.
 */
class EncounterJournalProvider implements JournalEntryProvider
{
    public function entriesFor(int $patientId, int $teamId): array
    {
        $appointments = Appointment::query()
            ->forTeam($teamId)
            ->where('patient_id', $patientId)
            ->with(['services', 'certificates'])
            ->orderByDesc('scheduled_at')
            ->get();

        $entries = [];
        foreach ($appointments as $a) {
            $lines = [];

            if (!empty($a->findings)) {
                $lines[] = 'Befund: ' . Str::limit((string) $a->findings, 240);
            } elseif (!empty($a->anamnesis)) {
                $lines[] = 'Anamnese: ' . Str::limit((string) $a->anamnesis, 240);
            }
            foreach ($a->services as $s) {
                $lines[] = 'Leistung: ' . $s->title . ($s->result ? ' — ' . $s->result : '');
            }
            foreach ($a->certificates as $c) {
                $lines[] = 'Bescheinigung: ' . $c->title;
            }

            $entries[] = [
                'date'     => $a->scheduled_at ?? $a->created_at,
                'anchor'   => 'appt-' . $a->id,
                'type'     => 'appointment',
                'icon'     => 'heroicon-o-calendar-days',
                'title'    => 'Termin',
                'subtitle' => $a->status?->label(),
                'badge'    => null,
                'lines'    => $lines,
                'url'      => route('encounter.appointments.show', $a->id),
            ];
        }

        return $entries;
    }
}
