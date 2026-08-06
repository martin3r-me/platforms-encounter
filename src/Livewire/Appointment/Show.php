<?php

namespace Platform\Encounter\Livewire\Appointment;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Appointment as AppointmentModel;
use Platform\Encounter\Models\Service as ServiceModel;
use Platform\Encounter\Enums\AppointmentStatus;
use Platform\Encounter\Enums\Audience;
use Platform\Encounter\Services\CertificateService;

class Show extends Component
{
    #[Locked]
    public int $appointmentId;

    public array $form = [];

    public bool $showServiceModal = false;
    public array $serviceForm = [
        'title' => '',
        'result' => '',
        'interval_active' => false,
        'interval_months' => null,
    ];

    public bool $showCertModal = false;
    public string $certAudience = 'patient';

    protected array $fields = [
        'scheduled_at', 'status', 'performed_by', 'doctor_stamp', 'notes',
        'anamnesis', 'findings', 'remarks', 'confidential',
    ];

    public function mount(int $appointment): void
    {
        $model = $this->resolve($appointment);
        $this->appointmentId = $model->id;

        foreach ($this->fields as $f) {
            $value = $model->{$f};
            if ($f === 'scheduled_at') {
                $value = optional($value)->format('Y-m-d\TH:i');
            }
            if ($f === 'status') {
                $value = $value instanceof AppointmentStatus ? $value->value : $value;
            }
            $this->form[$f] = $value;
        }
    }

    protected function resolve(int $id): AppointmentModel
    {
        $team = Auth::user()->currentTeam;

        return AppointmentModel::query()->forTeam($team->id)->findOrFail($id);
    }

    protected function rules(): array
    {
        return [
            'form.scheduled_at' => ['required', 'date'],
            'form.status'       => ['required', 'string'],
            'form.performed_by' => ['nullable', 'string', 'max:255'],
            'form.doctor_stamp' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $model = $this->resolve($this->appointmentId);

        $data = [];
        foreach ($this->fields as $f) {
            $data[$f] = $this->form[$f] === '' ? null : $this->form[$f];
        }

        $model->update($data);

        $this->dispatch('toast', message: 'Termin gespeichert.', type: 'success');
    }

    public function delete()
    {
        $this->resolve($this->appointmentId)->delete();

        return $this->redirectRoute('encounter.appointments.index', navigate: true);
    }

    public function addService(): void
    {
        $this->validate([
            'serviceForm.title'           => ['required', 'string', 'max:255'],
            'serviceForm.result'          => ['nullable', 'string', 'max:255'],
            'serviceForm.interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $appointment = $this->resolve($this->appointmentId);

        $intervalActive = (bool) ($this->serviceForm['interval_active'] ?? false);
        $intervalMonths = $this->serviceForm['interval_months'] ?: null;

        $nextDue = null;
        if ($intervalActive && $intervalMonths && $appointment->scheduled_at) {
            $nextDue = $appointment->scheduled_at->copy()->addMonths((int) $intervalMonths)->startOfDay();
        }

        ServiceModel::create([
            'appointment_id'  => $appointment->id,
            'title'           => trim((string) $this->serviceForm['title']),
            'result'          => $this->serviceForm['result'] ?: null,
            'interval_active' => $intervalActive,
            'interval_months' => $intervalMonths,
            'next_due'        => $nextDue,
        ]);

        $this->reset('serviceForm');
        $this->showServiceModal = false;
        $this->dispatch('toast', message: 'Leistung erfasst.', type: 'success');
    }

    public function removeService(int $serviceId): void
    {
        $appointment = $this->resolve($this->appointmentId);
        $appointment->services()->where('id', $serviceId)->delete();
    }

    public function issueCertificate()
    {
        $audience = Audience::tryFrom($this->certAudience);
        if (!$audience) {
            $this->addError('certAudience', 'Ungültige Zielgruppe.');
            return;
        }

        $appointment = $this->resolve($this->appointmentId);

        $certificate = app(CertificateService::class)->issue($appointment, $audience);

        $this->showCertModal = false;

        return $this->redirectRoute('encounter.certificates.show', ['certificate' => $certificate->id], navigate: true);
    }

    public function render()
    {
        $model = $this->resolve($this->appointmentId)->load(['patient', 'services', 'certificates']);

        return view('encounter::livewire.appointment.show', [
            'appointment'     => $model,
            'statusOptions'   => collect(AppointmentStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'audienceOptions' => collect(Audience::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
        ])->layout('platform::layouts.app');
    }
}
