<?php

namespace Platform\Encounter\Livewire\Record;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Patient\Models\Patient as PatientModel;
use Platform\Encounter\Services\JournalRegistry;

/**
 * Akte — die Verlauf-Ansicht eines Patienten: gemergter, datierter Strom aller
 * Fachmodul-Einträge (Termine, Vorsorge, Beschäftigung, später Labor), neueste zuerst.
 * Innere Sidebar = Verlauf-Marken (Sprung), rechts = Werte & Status.
 */
class Show extends Component
{
    #[Locked]
    public int $patientId;

    public function mount(int $patient): void
    {
        $this->patientId = $this->resolvePatient($patient)->id;
    }

    protected function resolvePatient(int $id): PatientModel
    {
        $team = (int) Auth::user()->currentTeam->id;

        return PatientModel::query()->forTeam($team)->findOrFail($id);
    }

    public function render()
    {
        $team    = (int) Auth::user()->currentTeam->id;
        $patient = $this->resolvePatient($this->patientId);

        $entries = resolve(JournalRegistry::class)->entriesFor($this->patientId, $team);

        // Werte & Status: fällige/offene Vorsorgen aus dem Verlauf ableiten (kein Extra-Coupling).
        $dueProvisions = array_values(array_filter(
            $entries,
            fn ($e) => ($e['type'] ?? null) === 'provision' && !empty($e['badge'])
        ));

        return view('encounter::livewire.record.show', [
            'patient'       => $patient,
            'entries'       => $entries,
            'dueProvisions' => $dueProvisions,
        ])->layout('platform::layouts.app');
    }
}
