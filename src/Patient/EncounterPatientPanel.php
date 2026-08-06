<?php

namespace Platform\Encounter\Patient;

use Platform\Patient\Contracts\PatientPanelProvider;
use Platform\Encounter\Models\Appointment;

/**
 * Steuert das „Termine"-Panel zur Patienten-Akte bei. encounter hängt an patient.
 */
class EncounterPatientPanel implements PatientPanelProvider
{
    public function panel(int $patientId, int $teamId): ?array
    {
        $appointments = Appointment::query()
            ->forTeam($teamId)
            ->where('patient_id', $patientId)
            ->with('services')
            ->orderByDesc('scheduled_at')
            ->limit(10)
            ->get();

        $items = $appointments->map(function (Appointment $a) {
            $anlass = $a->services->pluck('title')->filter()->implode(', ');

            return [
                'title'    => (optional($a->scheduled_at)->format('d.m.Y H:i') ?: '—')
                              . ' · ' . ($a->status?->label() ?? ''),
                'subtitle' => $anlass !== '' ? $anlass : null,
                'meta'     => null,
                'url'      => route('encounter.appointments.show', $a->id),
            ];
        })->all();

        return [
            'key'     => 'appointments',
            'title'   => 'Termine',
            'icon'    => 'calendar-days',
            'order'   => 20,
            'items'   => $items,
            'actions' => [
                ['label' => 'Neuer Termin', 'url' => route('encounter.appointments.index')],
            ],
            'empty'   => 'Noch keine Termine.',
        ];
    }
}
