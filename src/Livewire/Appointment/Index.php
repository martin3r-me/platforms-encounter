<?php

namespace Platform\Encounter\Livewire\Appointment;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Appointment as AppointmentModel;
use Platform\Encounter\Enums\AppointmentStatus;
use Platform\Patient\Models\Patient as PatientModel;

class Index extends Component
{
    public bool $showCreate = false;
    public ?int $patient_id = null;
    public ?string $scheduled_at = null;

    protected function rules(): array
    {
        return [
            'patient_id'   => ['required', 'integer'],
            'scheduled_at' => ['required', 'date'],
        ];
    }

    public function updatedShowCreate(): void
    {
        $this->reset(['patient_id', 'scheduled_at']);
        $this->resetValidation();
    }

    public function create()
    {
        $this->validate();

        $team = Auth::user()->currentTeam;

        // Patient muss zum Team gehören (Schweigepflicht/Isolation).
        $patient = PatientModel::query()->forTeam($team->id)->find($this->patient_id);
        if (!$patient) {
            $this->addError('patient_id', 'Patient nicht gefunden.');
            return;
        }

        $appointment = AppointmentModel::create([
            'patient_id'   => $patient->id,
            'scheduled_at' => $this->scheduled_at,
            'status'       => AppointmentStatus::Planned->value,
        ]);

        return $this->redirectRoute('encounter.appointments.show', ['appointment' => $appointment->id], navigate: true);
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $appointments = AppointmentModel::query()
            ->forTeam($team->id)
            ->with('patient')
            ->orderByDesc('scheduled_at')
            ->get();

        $patients = PatientModel::query()
            ->forTeam($team->id)
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return view('encounter::livewire.appointment.index', [
            'appointments'   => $appointments,
            'patientOptions' => $patients->mapWithKeys(fn ($p) => [$p->id => $p->getDisplayName()])->all(),
        ])->layout('platform::layouts.app');
    }
}
