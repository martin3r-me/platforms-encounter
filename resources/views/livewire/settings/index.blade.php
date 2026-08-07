{{--
    Encounter · Einstellungen — nx-Design-System.
    Team-anpassbare Referenz-/Konfig-Daten (Settings-Schicht). Start: Textbausteine.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Einstellungen" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Termine', 'route' => 'encounter.dashboard', 'icon' => 'calendar-days'],
            ['label' => 'Einstellungen'],
        ]">
            <x-nx-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Textbaustein</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-section icon="heroicon-o-document-text" title="Textbausteine"
                      description="Wiederverwendbare Bausteine, audience-getaggt. Fließen in Bescheinigungen der passenden Zielgruppe."
                      :hint="$blocks->count()">
            @if($blocks->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-document-text">
                        Noch keine Textbausteine. Lege den ersten über „Neuer Textbaustein" an.
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Titel</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Zielgruppe</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Aktiv</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right"></x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($blocks as $block)
                                <x-nx-table-row wire:key="tb-{{ $block->id }}">
                                    <x-nx-table-cell>{{ $block->title }}</x-nx-table-cell>
                                    <x-nx-table-cell><x-nx-badge>{{ $block->audience?->label() }}</x-nx-badge></x-nx-table-cell>
                                    <x-nx-table-cell>
                                        @if($block->active)
                                            <x-nx-badge variant="success" dot>Aktiv</x-nx-badge>
                                        @else
                                            <x-nx-badge dot>Inaktiv</x-nx-badge>
                                        @endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <div class="flex justify-end gap-2">
                                            <x-nx-button variant="ghost" size="xs" wire:click="openEdit({{ $block->id }})">
                                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                            </x-nx-button>
                                            <x-nx-button variant="danger" size="xs" wire:click="delete({{ $block->id }})"
                                                         wire:confirm="Textbaustein löschen?">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </x-nx-button>
                                        </div>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>

        {{-- Feld-Definitionen --}}
        <x-nx-section icon="heroicon-o-view-columns" title="Feld-Definitionen"
                      description="Team-anpassbare Felder für die Leistungserfassung, audience-getaggt."
                      :hint="$fields->count()">
            <x-slot name="action">
                <x-nx-button variant="secondary" size="sm" wire:click="openFieldCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neues Feld
                </x-nx-button>
            </x-slot>
            @if($fields->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-view-columns">Noch keine Feld-Definitionen.</x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Label</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Key</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Typ</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Zielgruppe</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right"></x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($fields as $field)
                                <x-nx-table-row wire:key="fd-{{ $field->id }}">
                                    <x-nx-table-cell>{{ $field->label }}</x-nx-table-cell>
                                    <x-nx-table-cell class="text-[color:var(--nx-muted)]">{{ $field->key }}</x-nx-table-cell>
                                    <x-nx-table-cell>{{ $field->type?->label() }}</x-nx-table-cell>
                                    <x-nx-table-cell><x-nx-badge>{{ $field->audience?->label() }}</x-nx-badge></x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <div class="flex justify-end gap-2">
                                            <x-nx-button variant="ghost" size="xs" wire:click="openFieldEdit({{ $field->id }})">
                                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                            </x-nx-button>
                                            <x-nx-button variant="danger" size="xs" wire:click="deleteField({{ $field->id }})"
                                                         wire:confirm="Feld löschen?">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </x-nx-button>
                                        </div>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>

        {{-- Anamnese-Fragenkatalog --}}
        <x-nx-section icon="heroicon-o-clipboard-document-list" title="Anamnese-Fragenkatalog"
                      description="Anlassabhängige Anamnese-Fragen (an ArbMedVV-Vorsorgeanlass gebunden) + untersucherabhängig."
                      :hint="$questions->count()">
            <x-slot name="action">
                <x-nx-button variant="secondary" size="sm" wire:click="openQuestionCreate">
                    @svg('heroicon-o-plus', 'w-4 h-4') Neue Frage
                </x-nx-button>
            </x-slot>
            @if($questions->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-clipboard-document-list">Noch keine Fragen. Lege die erste über „Neue Frage" an.</x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Frage</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Anlass</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Typ</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Untersucher</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right"></x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($questions as $q)
                                <x-nx-table-row wire:key="aq-{{ $q->id }}">
                                    <x-nx-table-cell>
                                        {{ $q->text }}
                                        @if($q->section)<span class="ml-1 text-xs text-[color:var(--nx-faint)]">· {{ $q->section }}</span>@endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell class="text-[color:var(--nx-muted)]">{{ $q->catalog?->title ?? 'allgemein' }}</x-nx-table-cell>
                                    <x-nx-table-cell><x-nx-badge>{{ $q->type?->label() }}</x-nx-badge></x-nx-table-cell>
                                    <x-nx-table-cell class="text-[color:var(--nx-muted)]">{{ $q->examiner_scope ?: 'alle' }}</x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <div class="flex justify-end gap-2">
                                            <x-nx-button variant="ghost" size="xs" wire:click="openQuestionEdit({{ $q->id }})">
                                                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                            </x-nx-button>
                                            <x-nx-button variant="danger" size="xs" wire:click="deleteQuestion({{ $q->id }})"
                                                         wire:confirm="Frage löschen?">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </x-nx-button>
                                        </div>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>

        {{-- Praxis-Profil --}}
        <x-nx-section icon="heroicon-o-building-storefront" title="Praxis-Profil"
                      description="Briefkopf für Bescheinigungen/PDF (ein Datensatz je Team).">
            <x-slot name="action">
                <x-nx-button variant="primary" size="sm" wire:click="savePractice">
                    @svg('heroicon-o-check', 'w-4 h-4') Speichern
                </x-nx-button>
            </x-slot>
            <x-nx-card>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-nx-input-text name="practiceForm.name" label="Praxisname" wire:model="practiceForm.name" />
                    <x-nx-input-text name="practiceForm.phone" label="Telefon" wire:model="practiceForm.phone" />
                    <x-nx-input-text name="practiceForm.street" label="Straße" wire:model="practiceForm.street" />
                    <x-nx-input-text name="practiceForm.postal_code" label="PLZ" wire:model="practiceForm.postal_code" />
                    <x-nx-input-text name="practiceForm.city" label="Ort" wire:model="practiceForm.city" />
                    <x-nx-input-text name="practiceForm.physician" label="Arzt/Ärztin" wire:model="practiceForm.physician" />
                    <x-nx-input-text name="practiceForm.physician_suffix" label="Zusatz (z. B. Facharzt)" wire:model="practiceForm.physician_suffix" />
                </div>
            </x-nx-card>
        </x-nx-section>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Bereiche</h3>
                    <div class="text-sm text-[color:var(--nx-text)]">Textbausteine</div>
                    <div class="text-sm text-[color:var(--nx-muted)]">Feld-Definitionen · Praxis-Profil (folgen)</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Letzte Aktivitäten</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">Keine Aktivitäten verfügbar.</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Textbaustein anlegen/bearbeiten --}}
    <x-nx-modal wire:model="showModal" size="lg">
        <x-slot name="header">{{ $editingId ? 'Textbaustein bearbeiten' : 'Neuer Textbaustein' }}</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="form.title" label="Titel" wire:model="form.title" required />
            <x-nx-input-select name="form.audience" label="Zielgruppe" wire:model="form.audience" :options="$audienceOptions" />
            <x-nx-input-textarea name="form.content" label="Inhalt" wire:model="form.content" rows="5" />
            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-number name="form.position" label="Position" wire:model="form.position" />
                <x-nx-input-checkbox name="form.active" label="Aktiv" wire:model="form.active" />
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showModal', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Feld-Definition anlegen/bearbeiten --}}
    <x-nx-modal wire:model="showFieldModal" size="lg">
        <x-slot name="header">{{ $editingFieldId ? 'Feld bearbeiten' : 'Neues Feld' }}</x-slot>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-text name="fieldForm.label" label="Label" wire:model="fieldForm.label" required />
                <x-nx-input-text name="fieldForm.key" label="Key" wire:model="fieldForm.key" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-select name="fieldForm.type" label="Typ" wire:model="fieldForm.type" :options="$typeOptions" />
                <x-nx-input-select name="fieldForm.audience" label="Zielgruppe" wire:model="fieldForm.audience" :options="$audienceOptions" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-nx-input-number name="fieldForm.position" label="Position" wire:model="fieldForm.position" />
                <x-nx-input-checkbox name="fieldForm.active" label="Aktiv" wire:model="fieldForm.active" />
            </div>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showFieldModal', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="saveField">Speichern</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Anamnese-Frage anlegen/bearbeiten --}}
    <x-nx-modal wire:model="showQuestionModal" size="lg">
        <x-slot name="header">{{ $editingQuestionId ? 'Frage bearbeiten' : 'Neue Anamnese-Frage' }}</x-slot>
        <div class="space-y-3">
            <x-nx-input-textarea name="questionForm.text" label="Frage" wire:model="questionForm.text" rows="2" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-nx-input-select name="questionForm.type" label="Antworttyp" wire:model="questionForm.type" :options="$questionTypeOptions" />
                <x-nx-input-select name="questionForm.occasion_id" label="Vorsorgeanlass (ArbMedVV)" wire:model="questionForm.occasion_id" :options="$occasionOptions" />
                <x-nx-input-text name="questionForm.examiner_scope" label="Untersucher (leer = alle)" wire:model="questionForm.examiner_scope" placeholder="z.B. arzt / assistenz" />
                <x-nx-input-text name="questionForm.section" label="Abschnitt (Gruppierung)" wire:model="questionForm.section" placeholder="z.B. Vorerkrankungen" />
                <x-nx-input-text name="questionForm.position" type="number" label="Position" wire:model="questionForm.position" />
                <label class="inline-flex items-center gap-2 text-sm text-[color:var(--nx-text)] mt-6">
                    <input type="checkbox" wire:model="questionForm.active" class="rounded border-[color:var(--nx-line)]" /> Aktiv
                </label>
            </div>
        </div>
        <x-slot name="footer">
            <x-nx-button variant="ghost" wire:click="$set('showQuestionModal', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveQuestion">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
