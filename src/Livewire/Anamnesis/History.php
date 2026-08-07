<?php

namespace Platform\Encounter\Livewire\Anamnesis;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Patient\Models\Patient as PatientModel;
use Platform\Encounter\Services\AnamnesisHistory;

/**
 * Anamnese-Verlauf (Stufe C) — versionierte Anamnese über alle Kontakte eines Patienten
 * als Matrix (Fragen × Kontakte). Änderungen ggü. dem zuletzt bekannten Wert werden
 * hervorgehoben (Delta-Historie). Read-only.
 */
class History extends Component
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

        $history = AnamnesisHistory::forPatient($this->patientId, $team);

        return view('encounter::livewire.anamnesis.history', [
            'patient'   => $patient,
            'contacts'  => $history['contacts'],
            'questions' => $history['questions'],
            'values'    => $history['values'],
            'changes'   => $history['changes'],
        ])->layout('platform::layouts.app');
    }
}
