<?php

namespace Platform\Encounter\Livewire\Appointment;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Appointment as AppointmentModel;
use Platform\Encounter\Models\Service as ServiceModel;
use Platform\Encounter\Models\Anamnesis as AnamnesisModel;
use Platform\Encounter\Models\AnamnesisQuestion;
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

    // --- Anamnese (Stufe B): strukturierte Erfassung je Termin ---
    public ?int $anamnesisId = null;
    /** Vorsorgeanlass (arbmedvv_occasion-ID als String; '' = ohne Anlass). Plain-Select → kein value=Label-Quirk. */
    public string $anamnesisOccasion = '';
    /** {question_id: value} */
    public array $anamnesisAnswers = [];
    public string $anamnesisFreeText = '';

    protected array $fields = [
        'scheduled_at', 'status', 'location_type', 'doctor_entity_id', 'performed_by', 'doctor_stamp', 'notes',
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
            if ($f === 'location_type') {
                $value = $value instanceof \Platform\Encounter\Enums\LocationType ? $value->value : ($value ?: 'practice');
            }
            $this->form[$f] = $value;
        }

        $this->loadAnamnesis($model);
    }

    /** Bestehende Anamnese des Termins laden (falls vorhanden). */
    protected function loadAnamnesis(AppointmentModel $model): void
    {
        $existing = AnamnesisModel::query()
            ->forTeam((int) $model->team_id)
            ->where('appointment_id', $model->id)
            ->latest('id')
            ->first();

        if (!$existing) {
            $this->anamnesisId       = null;
            $this->anamnesisOccasion = '';
            $this->anamnesisAnswers  = [];
            $this->anamnesisFreeText = '';
            return;
        }

        $this->anamnesisId       = $existing->id;
        $this->anamnesisOccasion = $existing->catalog_type === 'arbmedvv_occasion' && $existing->catalog_id
            ? (string) $existing->catalog_id
            : '';
        $this->anamnesisAnswers  = $existing->answers ?? [];
        $this->anamnesisFreeText = (string) ($existing->free_text ?? '');
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
            'form.location_type'    => ['nullable', 'string', 'in:practice,company,home,remote'],
            'form.doctor_entity_id' => ['nullable', 'integer'],
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
        $data['location_type']    = $this->form['location_type'] ?: 'practice';
        $data['doctor_entity_id'] = $this->form['doctor_entity_id'] ?: null;

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

    /**
     * Relevante Fragen: allgemeine (ohne Anlass) + die des gewählten Vorsorgeanlasses.
     * @return \Illuminate\Support\Collection<int,AnamnesisQuestion>
     */
    protected function relevantQuestions(int $team): \Illuminate\Support\Collection
    {
        $occasionId = ctype_digit($this->anamnesisOccasion) ? (int) $this->anamnesisOccasion : null;

        return AnamnesisQuestion::query()
            ->forTeam($team)->active()
            ->where(function ($q) use ($occasionId) {
                $q->whereNull('catalog_type');
                if ($occasionId) {
                    $q->orWhere(function ($qq) use ($occasionId) {
                        $qq->where('catalog_type', 'arbmedvv_occasion')->where('catalog_id', $occasionId);
                    });
                }
            })
            ->orderBy('section')->orderBy('position')->orderBy('id')
            ->get();
    }

    /** Anamnese des Termins speichern (updateOrCreate je Termin). */
    public function saveAnamnesis(): void
    {
        $model = $this->resolve($this->appointmentId);
        $team  = (int) $model->team_id;

        $occasionId = ctype_digit($this->anamnesisOccasion) ? (int) $this->anamnesisOccasion : null;

        // Nur Antworten auf tatsächlich relevante Fragen persistieren.
        $validIds = $this->relevantQuestions($team)->pluck('id')->all();
        $answers  = [];
        foreach ($this->anamnesisAnswers as $qid => $val) {
            if (in_array((int) $qid, $validIds, true) && $val !== '' && $val !== null) {
                $answers[(int) $qid] = $val;
            }
        }

        $anamnesis = $this->anamnesisId
            ? AnamnesisModel::query()->forTeam($team)->find($this->anamnesisId)
            : null;

        $data = [
            'patient_id'   => $model->patient_id,
            'catalog_type' => $occasionId ? 'arbmedvv_occasion' : null,
            'catalog_id'   => $occasionId,
            'answers'      => $answers,
            'free_text'    => $this->anamnesisFreeText ?: null,
        ];

        if ($anamnesis) {
            $anamnesis->update($data);
        } else {
            $anamnesis = AnamnesisModel::create(array_merge($data, [
                'team_id'        => $team,
                'appointment_id' => $model->id,
            ]));
            $this->anamnesisId = $anamnesis->id;
        }

        $this->dispatch('toast', message: 'Anamnese gespeichert.', type: 'success');
    }

    public function render()
    {
        $model = $this->resolve($this->appointmentId)->load(['patient', 'services', 'certificates']);
        $team  = (int) $model->team_id;

        // Vorsorgeanlässe (arbmedvv) guarded — plain-Select-Werte (ID) gegen den value=Label-Quirk.
        $occasionOptions = [];
        if (class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
            foreach (\Platform\Arbmedvv\Models\Occasion::query()->where('team_id', $team)->orderBy('title')->get() as $o) {
                $occasionOptions[(int) $o->id] = $o->title;
            }
        }

        return view('encounter::livewire.appointment.show', [
            'appointment'         => $model,
            'statusOptions'       => collect(AppointmentStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'audienceOptions'     => collect(Audience::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'locationTypeOptions' => \Platform\Encounter\Support\LocationTypes::allowed((int) Auth::user()->currentTeam->id),
            'doctorOptions'       => \Platform\Encounter\Support\Doctors::options((int) Auth::user()->currentTeam->id),
            'occasionOptions'     => $occasionOptions,
            'anamnesisQuestions'  => $this->relevantQuestions($team),
        ])->layout('platform::layouts.app');
    }
}
