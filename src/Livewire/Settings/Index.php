<?php

namespace Platform\Encounter\Livewire\Settings;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\TextBlock as TextBlockModel;
use Platform\Encounter\Models\FieldDefinition as FieldDefinitionModel;
use Platform\Encounter\Models\Practice as PracticeModel;
use Platform\Encounter\Enums\Audience;
use Platform\Encounter\Enums\FieldType;

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

        return view('encounter::livewire.settings.index', [
            'blocks'          => TextBlockModel::query()->forTeam($team)->orderBy('audience')->orderBy('position')->get(),
            'fields'          => FieldDefinitionModel::query()->forTeam($team)->orderBy('position')->get(),
            'audienceOptions' => collect(Audience::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
            'typeOptions'     => collect(FieldType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all(),
        ])->layout('platform::layouts.app');
    }
}
