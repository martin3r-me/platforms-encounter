<?php

namespace Platform\Encounter\Livewire\Cockpit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Encounter\Models\Appointment as AppointmentModel;
use Platform\Encounter\Services\JournalRegistry;
use Platform\Patient\Models\Patient as PatientModel;

/**
 * Sprechstunde-Cockpit — EINE ärztliche Oberfläche: Tages-Agenda (links) + Patienten-Akte
 * (rechts). Komponiert bestehende Bausteine: Agenda aus Appointment, Akte aus JournalRegistry
 * (Termine + Vorsorge + Beschäftigung, modulübergreifend). Der Arzt bleibt in einer View.
 */
class Show extends Component
{
    #[Url(as: 'd')]
    public string $date = '';

    #[Url(as: 'p')]
    public ?int $selectedPatientId = null;

    public ?int $selectedAppointmentId = null;

    /** Rechte Fläche: 'verlauf' (Akte) | 'stammdaten'. */
    public string $tab = 'verlauf';

    public function mount(): void
    {
        if ($this->date === '' || !$this->isValidDate($this->date)) {
            $this->date = now()->toDateString();
        }
    }

    protected function isValidDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }

    public function goPrev(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function goNext(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function goToday(): void
    {
        $this->date = now()->toDateString();
    }

    /** Termin wählen → rechts die Akte des zugehörigen Patienten laden. */
    public function selectAppointment(int $appointmentId): void
    {
        $appointment = AppointmentModel::query()
            ->forTeam((int) Auth::user()->currentTeam->id)
            ->findOrFail($appointmentId);

        $this->selectedAppointmentId = $appointmentId;
        $this->selectedPatientId = $appointment->patient_id;
        $this->tab = 'verlauf';
    }

    /** Patient direkt wählen (z. B. aus Suche) — ohne Termin. */
    public function selectPatient(int $patientId): void
    {
        $this->selectedAppointmentId = null;
        $this->selectedPatientId = $patientId;
        $this->tab = 'verlauf';
    }

    /** Bescheinigung aus dem gewählten Termin ausstellen (audience = patient|employer). */
    public function issueCertificate(string $audienceValue): void
    {
        if (!$this->selectedAppointmentId) {
            return;
        }

        $team = (int) Auth::user()->currentTeam->id;
        $appointment = AppointmentModel::query()->forTeam($team)
            ->with(['services', 'patient'])->find($this->selectedAppointmentId);

        $audience = \Platform\Encounter\Enums\Audience::tryFrom($audienceValue);

        if (!$appointment || !$audience) {
            return;
        }

        resolve(\Platform\Encounter\Services\CertificateService::class)->issue($appointment, $audience);

        $this->tab = 'verlauf';
        $this->dispatch('toast', message: 'Bescheinigung ausgestellt.', type: 'success');
    }

    public function render()
    {
        $team = (int) Auth::user()->currentTeam->id;

        $appointments = AppointmentModel::query()
            ->forTeam($team)
            ->with('patient')
            ->whereDate('scheduled_at', $this->date)
            ->orderBy('scheduled_at')
            ->get();

        $patient       = null;
        $grouped       = [];
        $dueProvisions = [];

        if ($this->selectedPatientId) {
            $patient = PatientModel::query()->forTeam($team)
                ->with(['phoneNumbers', 'emailAddresses', 'postalAddresses'])
                ->find($this->selectedPatientId);

            if ($patient) {
                $entries = resolve(JournalRegistry::class)->entriesFor((int) $patient->id, $team);

                foreach ($entries as $e) {
                    if (!empty($e['date'])) {
                        $grouped[$e['date']->format('Y-m-d')][] = $e;
                    }
                    if (($e['type'] ?? null) === 'provision' && !empty($e['badge'])) {
                        $dueProvisions[] = $e;
                    }
                }
            } else {
                $this->selectedPatientId = null; // stale/foreign id
            }
        }

        return view('encounter::livewire.cockpit.show', [
            'appointments'  => $appointments,
            'day'           => Carbon::parse($this->date),
            'isToday'       => $this->date === now()->toDateString(),
            'patient'       => $patient,
            'grouped'       => $grouped,
            'dueProvisions' => $dueProvisions,
        ])->layout('platform::layouts.app');
    }
}
