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
        'examination_id' => '',
        'result' => '',
        'interval_active' => false,
        'interval_months' => null,
    ];

    public bool $showCertModal = false;
    public string $certAudience = 'patient';

    // --- Anamnese (Stufe B): strukturierte Erfassung je Termin ---
    public ?int $anamnesisId = null;
    /** Gewählte Verfahren (examination-IDs) — treiben die Anamnese-Fragen. 1..n je Termin. */
    public array $selectedExaminationIds = [];
    /** Picker-Wert zum Hinzufügen eines Verfahrens. */
    public string $addExaminationId = '';
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
        $this->selectedExaminationIds = $this->loadExaminationIds($model);
    }

    /** @return array<int,int> examination-IDs der am Termin gewählten Verfahren */
    protected function loadExaminationIds(AppointmentModel $model): array
    {
        return $model->examinations()->pluck('examinations.id')->map(fn ($v) => (int) $v)->values()->all();
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
            $this->anamnesisAnswers  = [];
            $this->anamnesisFreeText = '';
            return;
        }

        $this->anamnesisId       = $existing->id;
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
            'serviceForm.title'           => ['nullable', 'string', 'max:255'],
            'serviceForm.examination_id'  => ['nullable'],
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

        // Untersuchungs-Katalog-Bindung (roter Faden: Leistung → examinations-Eintrag).
        $examId = ctype_digit((string) ($this->serviceForm['examination_id'] ?? '')) ? (int) $this->serviceForm['examination_id'] : null;
        $title  = trim((string) $this->serviceForm['title']);
        if ($title === '' && $examId && class_exists(\Platform\Examinations\Models\Examination::class)) {
            $exam = \Platform\Examinations\Models\Examination::query()->forTeam((int) $appointment->team_id)->find($examId);
            $title = $exam?->label() ?? '';
        }
        if ($title === '') {
            $this->addError('serviceForm.title', 'Titel oder Untersuchung wählen.');
            return;
        }

        ServiceModel::create([
            'appointment_id'  => $appointment->id,
            'catalog_type'    => $examId ? 'examination' : null,
            'catalog_id'      => $examId,
            'title'           => $title,
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

    /**
     * Produkt-Bündel (examinations) übernehmen: je enthaltener Untersuchung eine Leistung anlegen.
     * Bereits erfasste Untersuchungen werden übersprungen (kein Doppeln). Guarded — Modul optional.
     * Die Vermengungsgruppen-Prüfung bleibt lose (Banner in render(); harter Block erst bei Bescheinigung).
     */
    public function addBundle(int $bundleId): void
    {
        if (!class_exists(\Platform\Examinations\Models\ExaminationBundle::class)) {
            return;
        }

        $appointment = $this->resolve($this->appointmentId);
        $team        = (int) $appointment->team_id;

        $bundle = \Platform\Examinations\Models\ExaminationBundle::query()->forTeam($team)
            ->with('examinations')->find($bundleId);
        if (!$bundle) {
            return;
        }

        $existingIds = $appointment->services()
            ->where('catalog_type', 'examination')->pluck('catalog_id')
            ->filter()->map(fn ($v) => (int) $v)->all();

        $added = 0;
        foreach ($bundle->examinations as $exam) {
            if (in_array((int) $exam->id, $existingIds, true)) {
                continue; // schon erfasst — nicht doppeln
            }
            ServiceModel::create([
                'appointment_id' => $appointment->id,
                'catalog_type'   => 'examination',
                'catalog_id'     => (int) $exam->id,
                'title'          => $exam->label(),
            ]);
            $existingIds[] = (int) $exam->id;
            $added++;
        }

        $this->dispatch('toast',
            message: $added > 0
                ? "Bündel „{$bundle->name}“ übernommen ({$added} Leistung(en))."
                : 'Alle Leistungen dieses Bündels sind bereits erfasst.',
            type: $added > 0 ? 'success' : 'info');
    }

    public function issueCertificate()
    {
        $audience = Audience::tryFrom($this->certAudience);
        if (!$audience) {
            $this->addError('certAudience', 'Ungültige Zielgruppe.');
            return;
        }

        $appointment = $this->resolve($this->appointmentId);

        try {
            $certificate = app(CertificateService::class)->issue($appointment, $audience);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return;
        }

        $this->showCertModal = false;

        return $this->redirectRoute('encounter.certificates.show', ['certificate' => $certificate->id], navigate: true);
    }

    /** Verfahren zum Termin hinzufügen (treibt die Anamnese-Fragen). */
    public function addExamination(int $examinationId): void
    {
        $appointment = $this->resolve($this->appointmentId);

        if (class_exists(\Platform\Examinations\Models\Examination::class)) {
            $ok = \Platform\Examinations\Models\Examination::query()
                ->forTeam((int) $appointment->team_id)->whereKey($examinationId)->exists();
            if (!$ok) {
                return;
            }
        }

        $appointment->examinations()->syncWithoutDetaching([
            $examinationId => ['position' => count($this->selectedExaminationIds) + 1],
        ]);
        $this->selectedExaminationIds = $this->loadExaminationIds($appointment);
        $this->addExaminationId = '';
    }

    /** Verfahren vom Termin entfernen. */
    public function removeExamination(int $examinationId): void
    {
        $appointment = $this->resolve($this->appointmentId);
        $appointment->examinations()->detach($examinationId);
        $this->selectedExaminationIds = $this->loadExaminationIds($appointment);
    }

    /**
     * Relevante Fragen: Basismodul (verfahrensunabhängig) + Fragen ALLER gewählten Verfahren
     * (Vereinigung, Überschneidungen nur einmal).
     * @return \Illuminate\Support\Collection<int,AnamnesisQuestion>
     */
    protected function relevantQuestions(int $team): \Illuminate\Support\Collection
    {
        $examIds = array_values(array_unique(array_filter(array_map('intval', $this->selectedExaminationIds))));

        return AnamnesisQuestion::query()
            ->forTeam($team)->active()
            ->where(function ($q) use ($examIds) {
                $q->whereNull('catalog_type'); // Basismodul läuft immer mit
                if (!empty($examIds)) {
                    $q->orWhere(fn ($w) => $w->where('catalog_type', 'examination')->whereIn('catalog_id', $examIds));
                }
            })
            ->orderBy('section')->orderBy('position')->orderBy('id')
            ->get();
    }

    /**
     * Untersuchungs-Katalog (examinations) für die Leistungs-Bindung — guarded.
     * @return array<int,string> [examination_id => label]
     */
    protected function examinationOptions(int $team): array
    {
        if (!class_exists(\Platform\Examinations\Models\Examination::class)) {
            return [];
        }
        try {
            $out = [];
            foreach (\Platform\Examinations\Models\Examination::query()->forTeam($team)->active()
                        ->orderBy('number')->orderBy('title')->get() as $e) {
                $out[(int) $e->id] = $e->label();
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Produkt-Bündel (examinations) für den Schnell-Übernehmen-Picker — guarded, nur nicht-leere aktive.
     * @return array<int,string> [bundle_id => "Name (n)"]
     */
    protected function bundleOptions(int $team): array
    {
        if (!class_exists(\Platform\Examinations\Models\ExaminationBundle::class)) {
            return [];
        }
        try {
            $out = [];
            foreach (\Platform\Examinations\Models\ExaminationBundle::query()->forTeam($team)
                        ->where('status', 'active')->withCount('examinations')
                        ->orderBy('name')->get() as $b) {
                if ($b->examinations_count > 0) {
                    $out[(int) $b->id] = $b->name.' ('.$b->examinations_count.')';
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Anamnese des Termins speichern (updateOrCreate je Termin). */
    public function saveAnamnesis(): void
    {
        $model = $this->resolve($this->appointmentId);
        $team  = (int) $model->team_id;

        // Nur Antworten auf tatsächlich relevante Fragen persistieren.
        // Zusätzlich den Fragetext ZUM ANTWORTZEITPUNKT snapshotten (robust gegen spätere
        // Katalog-Änderungen).
        $relevant = $this->relevantQuestions($team);
        $validIds = $relevant->pluck('id')->all();
        $textById = $relevant->pluck('text', 'id')->all();
        $answers  = [];
        $snapshot = [];
        foreach ($this->anamnesisAnswers as $qid => $val) {
            $qid = (int) $qid;
            if (in_array($qid, $validIds, true) && $val !== '' && $val !== null) {
                $answers[$qid]  = $val;
                $snapshot[$qid] = $textById[$qid] ?? null;
            }
        }

        $anamnesis = $this->anamnesisId
            ? AnamnesisModel::query()->forTeam($team)->find($this->anamnesisId)
            : null;

        $data = [
            'patient_id'         => $model->patient_id,
            'catalog_type'       => null,   // Multi-Verfahren: keine Einzel-Katalog-Bindung an der Anamnese
            'catalog_id'         => null,
            'answers'            => $answers,
            'questions_snapshot' => $snapshot,
            'free_text'          => $this->anamnesisFreeText ?: null,
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

        // Verfahren am Termin: gewählte Modelle + Picker (aktive, nach Kategorie gruppiert, ohne bereits gewählte).
        $selectedExaminations = collect();
        $examinationPickerOptions = ['' => '— Verfahren hinzufügen …'];
        if (class_exists(\Platform\Examinations\Models\Examination::class)) {
            $kindLabels = ['vorsorge' => 'Vorsorge', 'eignung' => 'Eignung', 'fev' => 'FeV'];
            $rows = \Platform\Examinations\Models\Examination::query()->forTeam($team)->active()
                ->orderByRaw("FIELD(category_kind, 'vorsorge','eignung','fev')")
                ->orderBy('number')->orderBy('title')->get();
            $selectedExaminations = $rows->whereIn('id', $this->selectedExaminationIds)
                ->sortBy(fn ($e) => array_search((int) $e->id, $this->selectedExaminationIds, true))->values();
            foreach ($rows as $e) {
                if (in_array((int) $e->id, $this->selectedExaminationIds, true)) {
                    continue;
                }
                $name = $e->recommendation_name ?: $e->title;
                $kind = $kindLabels[$e->category_kind] ?? $e->category_kind;
                $examinationPickerOptions[(int) $e->id] = trim(($kind ? "[{$kind}] " : '') . ($e->number ? $e->number . ' · ' : '') . $name);
            }
        }

        // Vermengungsgruppen-Konflikt (z.B. Vorsorge + Eignung): die gewählten Verfahren gegen die Core-Registry.
        $combRefs = array_map(fn ($eid) => ['type' => 'examination', 'id' => (int) $eid], $this->selectedExaminationIds);
        $combGroups  = app(\Platform\Core\Support\CatalogCombinationRegistry::class)->groupsFor($combRefs);
        $groupLabels = config('examinations.combination_groups', ['vorsorge' => 'Vorsorge', 'eignung' => 'Eignung']);

        return view('encounter::livewire.appointment.show', array_merge([
            'appointment'         => $model,
            'combinationConflict'     => count($combGroups) > 1,
            'combinationConflictText' => implode(' + ', array_map(fn ($g) => $groupLabels[$g] ?? $g, $combGroups)),
            'statusOptions'       => collect(AppointmentStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'audienceOptions'     => collect(Audience::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'locationTypeOptions' => \Platform\Encounter\Support\LocationTypes::allowed((int) Auth::user()->currentTeam->id),
            'doctorOptions'       => \Platform\Encounter\Support\Doctors::options((int) Auth::user()->currentTeam->id),
            'selectedExaminations'     => $selectedExaminations,
            'examinationPickerOptions' => $examinationPickerOptions,
            'anamnesisQuestions'  => $this->relevantQuestions($team),
            'examinationOptions'  => $this->examinationOptions($team),
            'bundleOptions'       => $this->bundleOptions($team),
        ], $this->patientContext($model, $team)))->layout('platform::layouts.app');
    }

    /**
     * Patienten-Gesamtkontext für die rechte Sidebar des Termins — der Patient ist der Anker.
     * Guarded, damit der Termin auch ohne occupational/Schema-Drift lädt.
     *
     * @return array{ctxProvisions:\Illuminate\Support\Collection,ctxEmployment:mixed,ctxRecent:\Illuminate\Support\Collection}
     */
    protected function patientContext(AppointmentModel $model, int $team): array
    {
        $patientId = (int) $model->patient_id;

        $provisions = collect();
        $employment = null;
        if ($patientId && class_exists(\Platform\Occupational\Models\Provision::class)) {
            try {
                $provisions = \Platform\Occupational\Models\Provision::query()->forTeam($team)
                    ->where('patient_id', $patientId)->with('occasion')
                    ->orderByRaw('next_due_at is null')->orderBy('next_due_at')->limit(6)->get();
                $employment = \Platform\Occupational\Models\Employment::query()->forTeam($team)
                    ->where('patient_id', $patientId)->with('organizationEntity')
                    ->orderByDesc('active')->orderByDesc('started_at')->first();
            } catch (\Throwable $e) {
                // occupational nicht verfügbar / Schema-Drift.
            }
        }

        // Letzte Termine desselben Patienten (ohne den aktuellen).
        $recent = collect();
        if ($patientId) {
            try {
                $recent = AppointmentModel::query()->forTeam($team)
                    ->where('patient_id', $patientId)->whereKeyNot($model->id)
                    ->orderByDesc('scheduled_at')->limit(5)->get();
            } catch (\Throwable $e) {
            }
        }

        // Letzte Bescheinigungen des Patienten (Metadaten — keine Befunde, Schweigepflicht).
        $certificates = collect();
        if ($patientId) {
            try {
                $certificates = \Platform\Encounter\Models\Certificate::query()->forTeam($team)
                    ->where('patient_id', $patientId)->latest()->limit(4)->get();
            } catch (\Throwable $e) {
            }
        }

        return [
            'ctxProvisions'   => $provisions,
            'ctxEmployment'   => $employment,
            'ctxRecent'       => $recent,
            'ctxCertificates' => $certificates,
        ];
    }
}
