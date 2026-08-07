<?php

namespace Platform\Encounter\Livewire\Settings;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\TextBlock as TextBlockModel;
use Platform\Encounter\Models\FieldDefinition as FieldDefinitionModel;
use Platform\Encounter\Models\Practice as PracticeModel;
use Platform\Encounter\Models\AnamnesisQuestion as AnamnesisQuestionModel;
use Platform\Encounter\Enums\Audience;
use Platform\Encounter\Enums\FieldType;
use Platform\Encounter\Enums\QuestionType;

class Index extends Component
{
    // --- Textbausteine ---
    public bool $showModal = false;
    public ?int $editingId = null;
    public array $form = ['title' => '', 'content' => '', 'audience' => 'patient', 'position' => 0, 'active' => true];

    // --- Feld-Definitionen ---
    public bool $showFieldModal = false;
    public ?int $editingFieldId = null;
    public array $fieldForm = ['key' => '', 'label' => '', 'type' => 'text', 'audience' => 'internal', 'position' => 0, 'active' => true];

    // --- Anamnese-Fragenkatalog ---
    public bool $showQuestionModal = false;
    public ?int $editingQuestionId = null;
    public array $questionForm = ['text' => '', 'type' => 'yes_no', 'occasion_id' => '', 'examiner_scope' => '', 'section' => '', 'position' => 0, 'active' => true];

    // --- Praxis-Profil ---
    public array $practiceForm = [
        'name' => '', 'street' => '', 'postal_code' => '', 'city' => '',
        'phone' => '', 'physician' => '', 'physician_suffix' => '',
    ];

    public function mount(): void
    {
        $practice = PracticeModel::forTeamOrNew($this->teamId());
        foreach (array_keys($this->practiceForm) as $f) {
            $this->practiceForm[$f] = $practice->{$f};
        }
    }

    protected function teamId(): int
    {
        return (int) Auth::user()->currentTeam->id;
    }

    // ===== Textbausteine =====

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = ['title' => '', 'content' => '', 'audience' => 'patient', 'position' => 0, 'active' => true];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $block = TextBlockModel::query()->forTeam($this->teamId())->findOrFail($id);
        $this->editingId = $block->id;
        $this->form = [
            'title' => $block->title, 'content' => $block->content,
            'audience' => $block->audience?->value ?? 'patient',
            'position' => $block->position, 'active' => (bool) $block->active,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.title'    => ['required', 'string', 'max:255'],
            'form.content'  => ['nullable', 'string'],
            'form.audience' => ['required', 'string'],
            'form.position' => ['nullable', 'integer', 'min:0'],
        ]);
        if (!Audience::tryFrom($this->form['audience'])) {
            $this->addError('form.audience', 'Ungültige Zielgruppe.');
            return;
        }
        $data = [
            'title' => trim((string) $this->form['title']),
            'content' => $this->form['content'] ?: null,
            'audience' => $this->form['audience'],
            'position' => (int) ($this->form['position'] ?? 0),
            'active' => (bool) ($this->form['active'] ?? false),
        ];
        if ($this->editingId) {
            TextBlockModel::query()->forTeam($this->teamId())->findOrFail($this->editingId)->update($data);
        } else {
            TextBlockModel::create($data);
        }
        $this->showModal = false;
        $this->dispatch('toast', message: 'Textbaustein gespeichert.', type: 'success');
    }

    public function delete(int $id): void
    {
        TextBlockModel::query()->forTeam($this->teamId())->findOrFail($id)->delete();
    }

    // ===== Feld-Definitionen =====

    public function openFieldCreate(): void
    {
        $this->editingFieldId = null;
        $this->fieldForm = ['key' => '', 'label' => '', 'type' => 'text', 'audience' => 'internal', 'position' => 0, 'active' => true];
        $this->resetValidation();
        $this->showFieldModal = true;
    }

    public function openFieldEdit(int $id): void
    {
        $field = FieldDefinitionModel::query()->forTeam($this->teamId())->findOrFail($id);
        $this->editingFieldId = $field->id;
        $this->fieldForm = [
            'key' => $field->key, 'label' => $field->label,
            'type' => $field->type?->value ?? 'text',
            'audience' => $field->audience?->value ?? 'internal',
            'position' => $field->position, 'active' => (bool) $field->active,
        ];
        $this->resetValidation();
        $this->showFieldModal = true;
    }

    public function saveField(): void
    {
        $this->validate([
            'fieldForm.key'      => ['required', 'string', 'max:255'],
            'fieldForm.label'    => ['required', 'string', 'max:255'],
            'fieldForm.type'     => ['required', 'string'],
            'fieldForm.audience' => ['required', 'string'],
            'fieldForm.position' => ['nullable', 'integer', 'min:0'],
        ]);
        if (!FieldType::tryFrom($this->fieldForm['type']) || !Audience::tryFrom($this->fieldForm['audience'])) {
            $this->addError('fieldForm.type', 'Ungültiger Typ oder Zielgruppe.');
            return;
        }
        $data = [
            'key' => trim((string) $this->fieldForm['key']),
            'label' => trim((string) $this->fieldForm['label']),
            'type' => $this->fieldForm['type'],
            'audience' => $this->fieldForm['audience'],
            'position' => (int) ($this->fieldForm['position'] ?? 0),
            'active' => (bool) ($this->fieldForm['active'] ?? false),
        ];
        if ($this->editingFieldId) {
            FieldDefinitionModel::query()->forTeam($this->teamId())->findOrFail($this->editingFieldId)->update($data);
        } else {
            FieldDefinitionModel::create($data);
        }
        $this->showFieldModal = false;
        $this->dispatch('toast', message: 'Feld-Definition gespeichert.', type: 'success');
    }

    public function deleteField(int $id): void
    {
        FieldDefinitionModel::query()->forTeam($this->teamId())->findOrFail($id)->delete();
    }

    // ===== Anamnese-Fragenkatalog =====

    public function openQuestionCreate(): void
    {
        $this->questionForm = ['text' => '', 'type' => 'yes_no', 'occasion_id' => '', 'examiner_scope' => '', 'section' => '', 'position' => 0, 'active' => true];
        $this->editingQuestionId = null;
        $this->showQuestionModal = true;
    }

    public function openQuestionEdit(int $id): void
    {
        $q = AnamnesisQuestionModel::query()->forTeam($this->teamId())->findOrFail($id);
        $this->editingQuestionId = $q->id;
        $this->questionForm = [
            'text'           => $q->text,
            'type'           => $q->type?->value ?? 'yes_no',
            'occasion_id'    => $q->catalog_type === 'arbmedvv_occasion' ? (string) $q->catalog_id : '',
            'examiner_scope' => $q->examiner_scope ?? '',
            'section'        => $q->section ?? '',
            'position'       => (int) $q->position,
            'active'         => (bool) $q->active,
        ];
        $this->showQuestionModal = true;
    }

    public function saveQuestion(): void
    {
        $data = $this->validate([
            'questionForm.text'           => ['required', 'string', 'max:1000'],
            'questionForm.type'           => ['required', 'string', 'in:yes_no,text,scale,choice'],
            'questionForm.occasion_id'    => ['nullable', 'string', 'max:255'],
            'questionForm.examiner_scope' => ['nullable', 'string', 'max:24'],
            'questionForm.section'        => ['nullable', 'string', 'max:191'],
            'questionForm.position'       => ['nullable', 'integer'],
        ])['questionForm'];

        // occasion_id kann ID ODER Titel sein (Select-Rendering) → zur ID auflösen.
        $occasionId = $data['occasion_id'] ?: null;
        if ($occasionId !== null && !ctype_digit((string) $occasionId)
            && class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
            $occasionId = \Platform\Arbmedvv\Models\Occasion::query()
                ->where('team_id', $this->teamId())->where('title', $occasionId)->value('id');
        }
        $occasionId = $occasionId ? (int) $occasionId : null;

        $payload = [
            'team_id'            => $this->teamId(),
            'text'               => $data['text'],
            'type'               => $data['type'],
            'catalog_type'       => $occasionId ? 'arbmedvv_occasion' : null,
            'catalog_id'         => $occasionId,
            'examiner_scope'     => $data['examiner_scope'] ?: null,
            'section'            => $data['section'] ?: null,
            'position'           => (int) ($data['position'] ?? 0),
            'active'             => (bool) ($this->questionForm['active'] ?? true),
            'created_by_user_id' => Auth::id(),
        ];

        if ($this->editingQuestionId) {
            AnamnesisQuestionModel::query()->forTeam($this->teamId())->findOrFail($this->editingQuestionId)->update($payload);
        } else {
            AnamnesisQuestionModel::create($payload);
        }

        $this->showQuestionModal = false;
        $this->dispatch('toast', message: 'Frage gespeichert.', type: 'success');
    }

    public function deleteQuestion(int $id): void
    {
        AnamnesisQuestionModel::query()->forTeam($this->teamId())->findOrFail($id)->delete();
    }

    // ===== Praxis-Profil =====

    public function savePractice(): void
    {
        $this->validate([
            'practiceForm.name'       => ['nullable', 'string', 'max:255'],
            'practiceForm.postal_code' => ['nullable', 'string', 'max:32'],
        ]);
        $data = [];
        foreach ($this->practiceForm as $k => $v) {
            $data[$k] = $v === '' ? null : $v;
        }
        PracticeModel::query()->updateOrCreate(['team_id' => $this->teamId()], $data);
        $this->dispatch('toast', message: 'Praxis-Profil gespeichert.', type: 'success');
    }

    public function render()
    {
        $team = $this->teamId();

        // Anlass-Katalog (arbmedvv) guarded.
        $occasionOptions = ['' => '— allgemein (kein Anlass) —'];
        if (class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
            foreach (\Platform\Arbmedvv\Models\Occasion::query()->where('team_id', $team)->orderBy('title')->get() as $o) {
                $occasionOptions[$o->id] = $o->title;
            }
        }

        return view('encounter::livewire.settings.index', [
            'blocks'          => TextBlockModel::query()->forTeam($team)->orderBy('audience')->orderBy('position')->get(),
            'fields'          => FieldDefinitionModel::query()->forTeam($team)->orderBy('position')->get(),
            'questions'       => AnamnesisQuestionModel::query()->forTeam($team)->with('catalog')->orderBy('section')->orderBy('position')->get(),
            'audienceOptions' => collect(Audience::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'typeOptions'     => collect(FieldType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'questionTypeOptions' => QuestionType::options(),
            'occasionOptions'     => $occasionOptions,
        ])->layout('platform::layouts.app');
    }
}
