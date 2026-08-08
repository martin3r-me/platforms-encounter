<?php

namespace Platform\Encounter\Livewire\Record;

use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Patient\Models\Patient as PatientModel;
use Platform\Encounter\Services\JournalRegistry;

/**
 * Patientenakte — die EINE Personen-Sicht für den Arzt. Tabs komponieren die Fachmodule:
 * Verlauf (JournalRegistry) · Stammdaten (patient) · Betriebsärztlich (occupational,
 * guarded) · Bescheinigungen (encounter). Rechts: Werte & Status.
 */
class Show extends Component
{
    #[Locked]
    public int $patientId;

    #[Url(as: 't')]
    public string $tab = 'verlauf';

    public function mount(int $patient): void
    {
        $this->patientId = $this->resolvePatient($patient)->id;
        if (!in_array($this->tab, ['verlauf', 'stammdaten', 'betriebsaerztlich', 'bescheinigungen'], true)) {
            $this->tab = 'verlauf';
        }
    }

    protected function resolvePatient(int $id): PatientModel
    {
        $team = (int) Auth::user()->currentTeam->id;

        return PatientModel::query()->forTeam($team)->findOrFail($id);
    }

    public function render()
    {
        $team    = (int) Auth::user()->currentTeam->id;
        $patient = $this->resolvePatient($this->patientId)
            ->load(['phoneNumbers', 'emailAddresses', 'postalAddresses']);

        $entries = resolve(JournalRegistry::class)->entriesFor($this->patientId, $team);

        // Werte & Status (rechte Sidebar): fällige/offene Vorsorgen aus dem Verlauf.
        $dueProvisions = array_values(array_filter(
            $entries,
            fn ($e) => ($e['type'] ?? null) === 'provision' && !empty($e['badge'])
        ));

        $grouped = [];
        foreach ($entries as $e) {
            $grouped[$e['date']->format('Y-m-d')][] = $e;
        }

        // Betriebsärztlich (occupational) — guarded.
        $employments = collect();
        $provisions  = collect();
        if (class_exists(\Platform\Occupational\Models\Employment::class)) {
            try {
                $employments = \Platform\Occupational\Models\Employment::query()->forTeam($team)
                    ->where('patient_id', $this->patientId)->with('organizationEntity')
                    ->orderByDesc('active')->orderByDesc('started_at')->get();
                $provisions = \Platform\Occupational\Models\Provision::query()->forTeam($team)
                    ->where('patient_id', $this->patientId)->with('occasion')
                    ->orderByRaw('next_due_at is null')->orderBy('next_due_at')->get();
            } catch (\Throwable $e) {
                // occupational nicht verfügbar / Schema-Drift.
            }
        }

        // Bescheinigungen (encounter).
        $certificates = collect();
        try {
            $certificates = \Platform\Encounter\Models\Certificate::query()->forTeam($team)
                ->where('patient_id', $this->patientId)->latest()->get();
        } catch (\Throwable $e) {
        }

        return view('encounter::livewire.record.show', [
            'patient'       => $patient,
            'entries'       => $entries,
            'grouped'       => $grouped,
            'dueProvisions' => $dueProvisions,
            'employments'   => $employments,
            'provisions'    => $provisions,
            'certificates'  => $certificates,
        ])->layout('platform::layouts.app');
    }
}
