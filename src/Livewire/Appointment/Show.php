<?php

namespace Platform\Encounter\Livewire\Appointment;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Appointment as AppointmentModel;
use Platform\Encounter\Models\Service as ServiceModel;
use Platform\Encounter\Models\Anamnesis as AnamnesisModel;
use Platform\Encounter\Models\AnamnesisQuestion;
use Platform\Encounter\Models\PatientAnamnesisEntry;
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

    /** Inline-Ergebnisse je Leistung: {service_id: result}. Speichert on-blur. */
    public array $serviceResults = [];

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
        $this->prefillPersistentAnswers($model);
    }

    /** @return array<int,int> examination-IDs der am Termin gewählten Verfahren */
    protected function loadExaminationIds(AppointmentModel $model): array
    {
        return $model->examinations()->pluck('examinations.id')->map(fn ($v) => (int) $v)->values()->all();
    }

    /**
     * Dauerfakten fortschreiben: unbeantwortete 'persistent'-Fragen mit dem aktuell gültigen
     * patientenweiten Wert vorbelegen (der Arzt sieht den Bestand und bestätigt/ändert).
     */
    protected function prefillPersistentAnswers(AppointmentModel $model): void
    {
        $patientId = (int) $model->patient_id;
        if (!$patientId) {
            return;
        }
        $team = (int) $model->team_id;

        $persistentIds = $this->relevantQuestions($team)
            ->where('persistence', 'persistent')->pluck('id')->all();
        if (empty($persistentIds)) {
            return;
        }

        $entries = PatientAnamnesisEntry::query()->forTeam($team)->forPatient($patientId)->open()
            ->whereIn('question_id', $persistentIds)->get()->keyBy('question_id');

        foreach ($persistentIds as $qid) {
            $current = $this->anamnesisAnswers[$qid] ?? null;
            if (($current === null || $current === '') && ($e = $entries->get($qid))) {
                $this->anamnesisAnswers[$qid] = $e->value;
            }
        }
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

    /** Inline-Ergebnis einer Leistung on-blur speichern. */
    public function updatedServiceResults($value, $key): void
    {
        $appointment = $this->resolve($this->appointmentId);
        $appointment->services()->where('id', (int) $key)
            ->update(['result' => ($value !== '' && $value !== null) ? (string) $value : null]);
    }

    /**
     * Produkt-Bündel übernehmen: alle enthaltenen Verfahren als gewählte Verfahren aufnehmen
     * (inkl. Leistung + Recall). Bereits gewählte werden übersprungen. Guarded — Modul optional.
     */
    public function addBundle(int $bundleId): void
    {
        if (!class_exists(\Platform\Examinations\Models\ExaminationBundle::class)) {
            return;
        }

        $appointment = $this->resolve($this->appointmentId);

        $bundle = \Platform\Examinations\Models\ExaminationBundle::query()->forTeam((int) $appointment->team_id)
            ->with('examinations')->find($bundleId);
        if (!$bundle) {
            return;
        }

        $added = 0;
        foreach ($bundle->examinations as $exam) {
            if (in_array((int) $exam->id, $this->selectedExaminationIds, true)) {
                continue; // schon gewählt
            }
            $this->attachExamination($appointment, $exam);
            $this->selectedExaminationIds[] = (int) $exam->id; // Dedup/Position im Loop
            $added++;
        }

        $this->selectedExaminationIds = $this->loadExaminationIds($appointment);

        $this->dispatch('toast',
            message: $added > 0
                ? "Bündel „{$bundle->name}“ übernommen ({$added} Verfahren)."
                : 'Alle Verfahren dieses Bündels sind bereits gewählt.',
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

    /** Verfahren zum Termin hinzufügen (treibt Fragen + Leistung + Recall). */
    public function addExamination(int $examinationId): void
    {
        $appointment = $this->resolve($this->appointmentId);

        $exam = class_exists(\Platform\Examinations\Models\Examination::class)
            ? \Platform\Examinations\Models\Examination::query()
                ->forTeam((int) $appointment->team_id)->find($examinationId)
            : null;
        if (!$exam) {
            return;
        }

        $this->attachExamination($appointment, $exam);

        $this->selectedExaminationIds = $this->loadExaminationIds($appointment);
        $this->addExaminationId = '';
    }

    /** Ein Verfahren an den Termin hängen: Pivot (+ Art-Default) + Leistung vorbelegen + Recall ableiten. */
    protected function attachExamination(AppointmentModel $appointment, $exam): void
    {
        $examinationId = (int) $exam->id;

        // Art nur bei Vorsorge (Default Pflichtvorsorge); Eignung/FeV kennen keine Vorsorge-Art.
        $careType = ($exam->category_kind === 'vorsorge') ? 'mandatory' : null;

        $pos = (int) \Illuminate\Support\Facades\DB::table('encounter_appointment_examinations')
            ->where('appointment_id', $appointment->id)->max('position') + 1;
        $appointment->examinations()->syncWithoutDetaching([
            $examinationId => ['position' => $pos, 'care_type' => $careType],
        ]);

        // Leistung aus dem Verfahren vorbelegen — nur, wenn noch keine für dieses Verfahren existiert.
        $hasService = $appointment->services()
            ->where('catalog_type', 'examination')->where('catalog_id', $examinationId)->exists();
        if (!$hasService) {
            $name  = $exam->recommendation_name ?: $exam->title;
            $title = trim(($exam->number ? $exam->number . ' · ' : '') . (string) $name);
            ServiceModel::create([
                'appointment_id' => $appointment->id,
                'catalog_type'   => 'examination',
                'catalog_id'     => $examinationId,
                'title'          => $title !== '' ? $title : (string) $name,
            ]);
        }

        $this->applyRecall($appointment, $examinationId, $careType);
    }

    /** Art der Vorsorge je Verfahren setzen (Pflicht/Angebot/Wunsch/Nachgehend). */
    public function setCareType(int $examinationId, ?string $careType): void
    {
        $appointment = $this->resolve($this->appointmentId);
        $careType = in_array($careType, ['mandatory', 'offered', 'request', 'follow_up'], true) ? $careType : null;
        $appointment->examinations()->updateExistingPivot($examinationId, ['care_type' => $careType]);
        $this->applyRecall($appointment, $examinationId, $careType);
    }

    /**
     * Nächste Fälligkeit aus Grundsatz + Art + Erst/Folge ableiten und an der Leistung vorbelegen
     * (ärztlich überschreibbar). Nur Vorsorge; Nachgehende Vorsorge → kein wiederkehrender Recall.
     */
    protected function applyRecall(AppointmentModel $appointment, int $examinationId, ?string $careType): void
    {
        if (!class_exists(\Platform\Examinations\Models\Examination::class)) {
            return;
        }
        $exam = \Platform\Examinations\Models\Examination::query()
            ->forTeam((int) $appointment->team_id)->find($examinationId);
        if (!$exam || $exam->category_kind !== 'vorsorge') {
            return;
        }

        $service = $appointment->services()
            ->where('catalog_type', 'examination')->where('catalog_id', $examinationId)->first();
        if (!$service) {
            return;
        }

        // Nachgehende Vorsorge: kein wiederkehrender Recall.
        if ($careType === 'follow_up') {
            $service->update(['interval_active' => false, 'interval_months' => null, 'next_due' => null]);
            return;
        }

        // Erst- vs. Folgeuntersuchung: hat der Patient bereits einen ANDEREN Termin mit diesem Verfahren?
        $isFollowUp = false;
        if ($appointment->patient_id) {
            $isFollowUp = \Illuminate\Support\Facades\DB::table('encounter_appointment_examinations as ae')
                ->join('encounter_appointments as a', 'a.id', '=', 'ae.appointment_id')
                ->where('ae.examination_id', $examinationId)
                ->where('a.patient_id', $appointment->patient_id)
                ->where('a.team_id', $appointment->team_id)
                ->where('a.id', '!=', $appointment->id)
                ->exists();
        }

        $months = $isFollowUp
            ? ($exam->interval_followup_months ?: $exam->interval_first_months)
            : ($exam->interval_first_months ?: $exam->interval_followup_months);

        if (!$months) {
            return; // kein Standard-Intervall im Katalog → nichts vorbelegen
        }

        $base = \Illuminate\Support\Carbon::parse($appointment->scheduled_at ?: now());
        $service->update([
            'interval_active' => true,
            'interval_months' => (int) $months,
            'next_due'        => $base->copy()->addMonths((int) $months)->startOfDay(),
        ]);
    }

    /** Verfahren vom Termin entfernen. Die vorbelegte Leistung nur räumen, wenn noch leer (kein Ergebnis). */
    public function removeExamination(int $examinationId): void
    {
        $appointment = $this->resolve($this->appointmentId);
        $appointment->examinations()->detach($examinationId);

        $appointment->services()
            ->where('catalog_type', 'examination')->where('catalog_id', $examinationId)
            ->whereNull('result')->whereNull('assessment')->whereNull('next_due')
            ->delete();

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
            ->orderBy('persistence')  // 'persistent' vor 'snapshot' → Dauerzustand-Block zuerst
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

        $this->promoteDurableFacts($model, $relevant, $answers);

        $this->dispatch('toast', message: 'Anamnese gespeichert.', type: 'success');
    }

    /**
     * Dauerfakten fortschreiben: Antworten auf 'persistent'-Fragen patientenweit halten.
     * Unverändert → bestätigen (last_confirmed_*); geändert → alte Zeile beenden + neue öffnen.
     *
     * @param \Illuminate\Support\Collection<int,AnamnesisQuestion> $relevant
     * @param array<int,mixed> $answers  bereits gefilterte Antworten {question_id: value}
     */
    protected function promoteDurableFacts(AppointmentModel $model, \Illuminate\Support\Collection $relevant, array $answers): void
    {
        $patientId = (int) $model->patient_id;
        if (!$patientId) {
            return;
        }
        $team  = (int) $model->team_id;
        $today = now()->toDateString();

        foreach ($relevant as $q) {
            if ($q->persistence !== 'persistent') {
                continue;
            }
            $val = $answers[$q->id] ?? null;
            if ($val === null || $val === '') {
                continue; // leere Antwort schreibt keinen Dauerfakt fort (kein Auto-Beenden)
            }

            $open = PatientAnamnesisEntry::query()->forTeam($team)->forPatient($patientId)->open()
                ->where('question_id', $q->id)->latest('id')->first();

            if ($open && (string) $open->value === (string) $val) {
                $open->update([
                    'last_confirmed_appointment_id' => $model->id,
                    'last_confirmed_at'             => now(),
                ]);
                continue;
            }

            if ($open) {
                $open->update(['valid_until' => $today]); // ändern: alten Bestand beenden
            }

            PatientAnamnesisEntry::create([
                'team_id'                       => $team,
                'patient_id'                    => $patientId,
                'question_id'                   => $q->id,
                'question_snapshot'             => $q->text,
                'value'                         => $val,
                'valid_from'                    => $today,
                'first_appointment_id'          => $model->id,
                'last_confirmed_appointment_id' => $model->id,
                'last_confirmed_at'             => now(),
            ]);
        }
    }

    public function render()
    {
        $model = $this->resolve($this->appointmentId)->load(['patient', 'services', 'certificates']);
        $team  = (int) $model->team_id;

        // Inline-Ergebnisse vorbelegen (ohne bereits im Formular editierte Werte zu überschreiben).
        foreach ($model->services as $s) {
            if (!array_key_exists($s->id, $this->serviceResults)) {
                $this->serviceResults[$s->id] = (string) ($s->result ?? '');
            }
        }

        // Verfahren am Termin: gewählte Modelle + Picker (aktive, nach Kategorie gruppiert, ohne bereits gewählte).
        $selectedExaminations = collect();
        $examinationPickerOptions = []; // Liste ['value'=>id,'label'=>…] für x-nx-input-select
        if (class_exists(\Platform\Examinations\Models\Examination::class)) {
            $kindLabels = ['vorsorge' => 'Vorsorge', 'eignung' => 'Eignung', 'fev' => 'FeV'];
            // Gewählte Verfahren MIT Pivot (care_type, position).
            $selectedExaminations = $model->examinations()->get();
            foreach (\Platform\Examinations\Models\Examination::query()->forTeam($team)->active()
                        ->orderByRaw("FIELD(category_kind, 'vorsorge','eignung','fev')")
                        ->orderBy('number')->orderBy('title')->get() as $e) {
                if (in_array((int) $e->id, $this->selectedExaminationIds, true)) {
                    continue;
                }
                $name = $e->recommendation_name ?: $e->title;
                $kind = $kindLabels[$e->category_kind] ?? $e->category_kind;
                $examinationPickerOptions[] = [
                    'value' => (int) $e->id,
                    'label' => trim(($kind ? "[{$kind}] " : '') . ($e->number ? $e->number . ' · ' : '') . $name),
                ];
            }
        }

        // Art der Vorsorge (Screenshot) — Labels wie im Bestand.
        $careTypeOptions = [
            'mandatory' => 'Pflichtvorsorge',
            'offered'   => 'Angebotsvorsorge',
            'request'   => 'Wunschvorsorge',
            'follow_up' => 'Nachgehende Vorsorge',
        ];

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
            'doctorOptions'       => collect(\Platform\Encounter\Support\Doctors::options((int) Auth::user()->currentTeam->id))
                                        ->map(fn ($n, $id) => ['value' => (int) $id, 'label' => $n])->values()->all(),
            'selectedExaminations'     => $selectedExaminations,
            'examinationPickerOptions' => $examinationPickerOptions,
            'careTypeOptions'          => $careTypeOptions,
            'anamnesisQuestions'  => $this->relevantQuestions($team),
            'bundleOptions'       => collect($this->bundleOptions($team))
                                        ->map(fn ($l, $id) => ['value' => (int) $id, 'label' => $l])->values()->all(),
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
