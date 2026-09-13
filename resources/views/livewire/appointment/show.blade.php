{{--
    Encounter · Termin-Detail/Bearbeiten — nx-Design-System.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Termin · ' . ($appointment->patient?->getDisplayName() ?? '—')" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_values(array_filter([
            ['label' => 'Sprechstunde', 'route' => 'encounter.cockpit', 'icon' => 'calendar-days'],
            $appointment->patient ? ['label' => $appointment->patient->getDisplayName() ?? '—', 'route' => 'encounter.akte.show', 'params' => ['patient' => $appointment->patient->id], 'icon' => 'folder-open'] : null,
            ['label' => optional($appointment->scheduled_at)->format('d.m.Y H:i') ?? 'Termin'],
        ]))">
            @if($appointment->patient)
                <x-nx-button variant="ghost" size="sm" :href="route('encounter.akte.show', $appointment->patient->id)" wire:navigate>
                    @svg('heroicon-o-folder-open', 'w-4 h-4')
                    <span>Zur Akte</span>
                </x-nx-button>
            @endif
            <x-nx-button variant="primary" size="sm" wire:click="save">
                @svg('heroicon-o-check', 'w-4 h-4')
                <span>Speichern</span>
            </x-nx-button>
            <x-nx-button variant="danger" size="sm" wire:click="delete"
                         wire:confirm="Diesen Termin wirklich löschen?">
                @svg('heroicon-o-trash', 'w-4 h-4')
                <span>Löschen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        {{-- Vermengungs-Konflikt (z.B. Vorsorge + Eignung) --}}
        @if($combinationConflict)
            <div class="rounded-lg border border-[#e0b878] bg-[#fbf1e0] px-4 py-3 text-sm text-[#7a4a12]">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 shrink-0')
                    <div>
                        <span class="font-semibold">Vermengungs-Konflikt: {{ $combinationConflictText }}</span>
                        <div class="mt-0.5 opacity-80">Diese Gruppen dürfen nach ArbMedVV nicht im selben Termin (oder auf einer Bescheinigung) geführt werden — bitte auf zwei separate Termine aufteilen.</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Termin --}}
        <x-nx-section icon="heroicon-o-calendar-days" title="Termin">
            <x-nx-card>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-nx-input-datetime name="form.scheduled_at" label="Termin" wire:model="form.scheduled_at" />
                    <x-nx-input-select name="form.status" label="Status" wire:model="form.status" :options="$statusOptions" />
                    <x-nx-input-select name="form.location_type" label="Ort" wire:model="form.location_type" :options="$locationTypeOptions" />
                    @if(empty($doctorOptions))
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Behandler</label>
                            <div class="text-sm text-[color:var(--nx-muted)] py-2">Keine Ärzte gepflegt (Praxis → Ärzte).</div>
                        </div>
                    @else
                        <x-nx-input-select name="form.doctor_entity_id" label="Behandler" wire:model="form.doctor_entity_id"
                                           :options="$doctorOptions" nullable nullLabel="— kein Behandler —" />
                    @endif
                    <x-nx-input-text name="form.performed_by" label="Durchgeführt von" wire:model="form.performed_by" />
                    <x-nx-input-text name="form.doctor_stamp" label="Arztstempel" wire:model="form.doctor_stamp" />
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- Anamnese (Stufe B): strukturierter Fragenkatalog, anlassbezogen --}}
        <x-nx-section icon="heroicon-o-clipboard-document-list" title="Anamnese (Fragenkatalog)"
                      description="Fragen = Basismodul + die der gewählten Verfahren (Vereinigung). Verschlüsselt gespeichert (Schweigepflicht).">
            <x-slot name="action">
                <x-nx-button variant="primary" size="sm" wire:click="saveAnamnesis">
                    @svg('heroicon-o-check', 'w-4 h-4') Anamnese speichern
                </x-nx-button>
            </x-slot>
            <x-nx-card>
                <div class="space-y-5">
                    {{-- Verfahren am Termin (1..n) — treiben die Fragen --}}
                    <div>
                        <label class="block text-sm mb-1 text-[color:var(--nx-text)]">Untersuchungen (Verfahren)</label>
                        @if($selectedExaminations->isEmpty())
                            <div class="text-sm text-[color:var(--nx-muted)] py-1">Noch kein Verfahren gewählt — es erscheinen nur die Basis-Fragen.</div>
                        @else
                            <div class="space-y-2 mb-2">
                                @foreach($selectedExaminations as $e)
                                    <div class="flex items-center gap-2" wire:key="selex-{{ $e->id }}">
                                        <span class="flex-1 text-sm text-[color:var(--nx-text)]">
                                            {{ trim(($e->number ? $e->number.' · ' : '').($e->recommendation_name ?? $e->title)) }}
                                        </span>
                                        @if($e->category_kind === 'vorsorge')
                                            <div class="w-52 shrink-0">
                                                <x-nx-input-select :options="$careTypeOptions" size="sm"
                                                                   value="{{ $e->pivot->care_type ?? 'mandatory' }}"
                                                                   x-on:change="$wire.setCareType({{ $e->id }}, $event.target.value)" />
                                            </div>
                                        @else
                                            <span class="text-xs text-[color:var(--nx-faint)]">{{ ['eignung'=>'Eignung','fev'=>'FeV'][$e->category_kind] ?? '' }}</span>
                                        @endif
                                        <button type="button" wire:click="removeExamination({{ $e->id }})"
                                                class="text-[color:var(--nx-faint)] hover:text-[color:var(--nx-danger)]">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @if(count($examinationPickerOptions) > 0)
                                <x-nx-input-select :options="$examinationPickerOptions" nullable nullLabel="+ Verfahren hinzufügen …"
                                                   x-on:change="if ($event.target.value) { $wire.addExamination($event.target.value) }" />
                            @endif
                            @if(!empty($bundleOptions))
                                <x-nx-input-select :options="$bundleOptions" nullable nullLabel="+ Bündel übernehmen …"
                                                   x-on:change="if ($event.target.value) { $wire.addBundle($event.target.value) }" />
                            @endif
                        </div>
                    </div>

                    {{-- Relevante Fragen (Basis + gewählte Verfahren) --}}
                    @if($anamnesisQuestions->isEmpty())
                        <x-nx-empty icon="heroicon-o-question-mark-circle">
                            Noch keine Fragen. Wähle ein Verfahren oder pflege den Fragenkatalog unter Praxis → Einstellungen.
                        </x-nx-empty>
                    @else
                        @php($currentPersistence = null)
                        <div class="space-y-4">
                            @foreach($anamnesisQuestions as $q)
                                @if($q->persistence !== $currentPersistence)
                                    @php($currentPersistence = $q->persistence)
                                    <div class="pt-2 text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] border-b border-[color:var(--nx-line)] pb-1">
                                        {{ $q->persistence === 'persistent' ? 'Dauerzustand (patientenweit)' : 'Momentaufnahme (dieser Termin)' }}
                                    </div>
                                @endif
                                <div wire:key="anq-{{ $q->id }}">
                                    <label class="block text-sm mb-1 text-[color:var(--nx-text)]">
                                        {{ $q->text }}
                                        @if($q->section)
                                            <span class="text-xs text-[color:var(--nx-faint)]">· {{ $q->section }}</span>
                                        @endif
                                    </label>
                                    @php($qt = $q->type instanceof \Platform\Encounter\Enums\QuestionType ? $q->type->value : $q->type)
                                    @if($qt === 'yes_no')
                                        <x-nx-input-select name="anamnesisAnswers.{{ $q->id }}" wire:model="anamnesisAnswers.{{ $q->id }}"
                                                           :options="['ja' => 'Ja', 'nein' => 'Nein', 'unbekannt' => 'Unbekannt']"
                                                           nullable nullLabel="—" />
                                    @elseif($qt === 'choice')
                                        <x-nx-input-select name="anamnesisAnswers.{{ $q->id }}" wire:model="anamnesisAnswers.{{ $q->id }}"
                                                           :options="$q->options ?? []" nullable nullLabel="—" />
                                    @elseif($qt === 'scale')
                                        <x-nx-input-number name="anamnesisAnswers.{{ $q->id }}" wire:model="anamnesisAnswers.{{ $q->id }}" />
                                    @else
                                        <x-nx-input-text name="anamnesisAnswers.{{ $q->id }}" wire:model="anamnesisAnswers.{{ $q->id }}" />
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <x-nx-input-textarea name="anamnesisFreeText" label="Ergänzende Angaben (Freitext)"
                                         wire:model="anamnesisFreeText" rows="3" />
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- Klinischer Freitext (verschlüsselt) --}}
        <x-nx-section icon="heroicon-o-lock-closed" title="Befund & Notizen (Freitext)"
                      description="Verschlüsselt gespeichert (Schweigepflicht).">
            <x-nx-card>
                <div class="space-y-4">
                    <x-nx-input-textarea name="form.anamnesis" label="Anamnese (Freitext)" wire:model="form.anamnesis" rows="4" />
                    <x-nx-input-textarea name="form.findings" label="Befund" wire:model="form.findings" rows="4" />
                    <x-nx-input-textarea name="form.remarks" label="Bemerkungen" wire:model="form.remarks" rows="3" />
                    <x-nx-input-textarea name="form.confidential" label="Vertraulich" wire:model="form.confidential" rows="3" />
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- Erbrachte Leistungen --}}
        <x-nx-section icon="heroicon-o-clipboard-document-check" title="Erbrachte Leistungen"
                      description="Aus den gewählten Verfahren — plus Praxis-Katalog & freie Leistungen."
                      :hint="$appointment->services->count()">
            {{-- Hinzufügen: Praxis-Katalog-Leistung / freie Leistung (Freitext) --}}
            <div class="mb-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                @if(!empty($practiceServiceOptions))
                    <x-nx-input-select :options="$practiceServiceOptions" nullable nullLabel="+ Praxis-Leistung …"
                                       x-on:change="if ($event.target.value) { $wire.addPracticeService($event.target.value) }" />
                @else
                    <div class="text-xs text-[color:var(--nx-muted)] self-center">Kein Praxis-Katalog gepflegt (Praxis → Einstellungen).</div>
                @endif
                <div class="flex gap-2">
                    <input type="text" wire:model="newFreeService" placeholder="Freie Leistung (Freitext) …"
                           x-on:keydown.enter.prevent="$wire.addFreeService()"
                           class="flex-1 rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] text-sm px-3 py-2 text-[color:var(--nx-text)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)] focus:border-[color:var(--nx-accent)]" />
                    <x-nx-button variant="secondary" size="sm" wire:click="addFreeService">
                        @svg('heroicon-o-plus', 'w-4 h-4') Hinzufügen
                    </x-nx-button>
                </div>
            </div>
            @if($appointment->services->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-clipboard-document-list">
                        Noch keine Leistungen — wähle oben im Anamnese-Abschnitt ein Verfahren.
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Leistung</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Ergebnis</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Nächste Fälligkeit</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right"></x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($appointment->services as $service)
                                <x-nx-table-row wire:key="svc-{{ $service->id }}">
                                    <x-nx-table-cell>
                                        {{ $service->title }}
                                        @if($service->catalog_type === 'examination')
                                            <x-nx-badge variant="info" size="xs">Katalog</x-nx-badge>
                                        @endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        <input type="text" wire:model.blur="serviceResults.{{ $service->id }}"
                                               placeholder="Ergebnis …"
                                               class="block w-full rounded-md border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-sm px-2 py-1 text-[color:var(--nx-text)]" />
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        @if($service->next_due)
                                            {{ $service->next_due->format('d.m.Y') }}
                                            @php($rc = $service->recallStatus())
                                            @if($rc === 'overdue')
                                                <x-nx-badge variant="danger" size="xs" dot>Überfällig</x-nx-badge>
                                            @elseif($rc === 'due')
                                                <x-nx-badge variant="warning" size="xs" dot>Fällig</x-nx-badge>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <x-nx-button variant="danger" size="xs" wire:click="removeService({{ $service->id }})"
                                                     wire:confirm="Leistung entfernen?">
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </x-nx-button>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>

        {{-- Bescheinigungen --}}
        <x-nx-section icon="heroicon-o-document-check" title="Bescheinigungen"
                      :hint="$appointment->certificates->count()">
            <x-slot name="action">
                <x-nx-button variant="secondary" size="sm" wire:click="$set('showCertModal', true)">
                    @svg('heroicon-o-document-plus', 'w-4 h-4') Ausstellen
                </x-nx-button>
            </x-slot>
            @if($appointment->certificates->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-document-text">
                        Noch keine Bescheinigung. „Ausstellen" friert einen audience-gefilterten Snapshot ein.
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                    @foreach($appointment->certificates as $certificate)
                        <x-nx-list-item :href="route('encounter.certificates.show', $certificate->id)"
                                        icon="heroicon-o-document-check"
                                        :title="$certificate->title"
                                        :subtitle="$certificate->audience?->label()"
                                        :meta="optional($certificate->created_at)->format('d.m.Y')" />
                    @endforeach
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Patient --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Patient</h3>
                    @if($appointment->patient)
                        @php($pt = $appointment->patient)
                        <a href="{{ route('encounter.akte.show', $pt->id) }}" wire:navigate
                           class="text-sm font-medium text-[color:var(--nx-accent)] hover:underline">
                            {{ $pt->getDisplayName() }}
                        </a>
                        <div class="mt-1 text-xs text-[color:var(--nx-muted)] space-y-0.5">
                            @if($pt->birth_date)
                                <div>geb. {{ \Illuminate\Support\Carbon::parse($pt->birth_date)->format('d.m.Y') }} ({{ \Illuminate\Support\Carbon::parse($pt->birth_date)->age }} J.)</div>
                            @endif
                            @if($pt->gender)
                                <div>{{ ['male'=>'männlich','female'=>'weiblich','diverse'=>'divers','m'=>'männlich','w'=>'weiblich','d'=>'divers'][$pt->gender] ?? $pt->gender }}</div>
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-[color:var(--nx-muted)]">—</div>
                    @endif
                </div>

                {{-- Kontakt --}}
                @if($appointment->patient)
                    @php($pt = $appointment->patient)
                    @if($pt->phone || $pt->phone_private || $pt->email_work || $pt->email_private || $pt->health_insurance)
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-2">Kontakt</h3>
                            <div class="text-sm text-[color:var(--nx-text)] space-y-0.5">
                                @if($pt->phone || $pt->phone_private)<div>{{ $pt->phone ?: $pt->phone_private }}</div>@endif
                                @if($pt->email_work || $pt->email_private)<div class="truncate">{{ $pt->email_work ?: $pt->email_private }}</div>@endif
                            </div>
                            @if($pt->health_insurance)
                                <div class="text-xs text-[color:var(--nx-muted)] mt-1">KV: {{ $pt->health_insurance }}</div>
                            @endif
                        </div>
                    @endif
                @endif

                {{-- Beschäftigung --}}
                @if($ctxEmployment)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-2">Beschäftigung</h3>
                        <div class="text-sm text-[color:var(--nx-text)]">{{ $ctxEmployment->organizationEntity->name ?? '—' }}</div>
                        <div class="text-xs text-[color:var(--nx-muted)] space-y-0.5 mt-0.5">
                            @if($ctxEmployment->position)<div>{{ $ctxEmployment->position }}</div>@endif
                            @if($ctxEmployment->personnel_number)<div>Pers-Nr. {{ $ctxEmployment->personnel_number }}</div>@endif
                            @if($ctxEmployment->started_at)<div>beschäftigt seit {{ \Illuminate\Support\Carbon::parse($ctxEmployment->started_at)->format('m.Y') }}</div>@endif
                        </div>
                        @if($ctxEmployment->first_aider)
                            <div class="mt-1"><x-nx-badge variant="success" dot>Ersthelfer</x-nx-badge></div>
                        @endif
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Patienten-Kontext" icon="heroicon-o-identification" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                @if($appointment->patient)
                    <a href="{{ route('encounter.akte.show', $appointment->patient->id) }}" wire:navigate
                       class="flex items-center justify-center gap-2 w-full rounded-md border border-[color:var(--nx-line)] px-3 py-2 text-sm font-medium text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition-colors">
                        @svg('heroicon-o-folder-open', 'w-4 h-4') Volle Akte öffnen
                    </a>
                    <a href="{{ route('encounter.anamnesis.timeline', $appointment->patient->id) }}" wire:navigate
                       class="mt-2 flex items-center justify-center gap-2 w-full rounded-md border border-[color:var(--nx-line)] px-3 py-2 text-sm font-medium text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition-colors">
                        @svg('heroicon-o-chart-bar', 'w-4 h-4') Dauerfakten-Zeitstrahl
                    </a>
                @endif

                {{-- Gefährdung (arbeitsmedizinisch relevant) --}}
                @if($ctxEmployment && $ctxEmployment->risk)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-2">Gefährdung</h3>
                        <div class="text-sm text-[color:var(--nx-text)]">{{ $ctxEmployment->risk }}</div>
                    </div>
                @endif

                {{-- Fällige / offene Vorsorgen — die Handlungspunkte --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Vorsorgen</h3>
                    @if($ctxProvisions->isEmpty())
                        <div class="text-sm text-[color:var(--nx-muted)]">Keine Vorsorgen hinterlegt.</div>
                    @else
                        <ul class="space-y-2">
                            @foreach($ctxProvisions as $p)
                                @php($due = $p->next_due_at ? \Illuminate\Support\Carbon::parse($p->next_due_at) : null)
                                @php($overdue = $due && $due->isPast())
                                <li class="flex items-start justify-between gap-2">
                                    <span class="text-sm text-[color:var(--nx-text)] min-w-0 truncate">{{ $p->occasion->title ?? 'Vorsorge' }}</span>
                                    @if($due)
                                        <x-nx-badge :variant="$overdue ? 'danger' : 'default'" dot>{{ $due->format('d.m.Y') }}</x-nx-badge>
                                    @else
                                        <span class="text-xs text-[color:var(--nx-faint)] shrink-0">offen</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Bescheinigungen (Metadaten — keine Befunde, Schweigepflicht) --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Bescheinigungen</h3>
                    @if($ctxCertificates->isEmpty())
                        <div class="text-sm text-[color:var(--nx-muted)]">Keine Bescheinigungen.</div>
                    @else
                        <ul class="space-y-1">
                            @foreach($ctxCertificates as $c)
                                <li>
                                    <a href="{{ route('encounter.certificates.show', $c->id) }}" wire:navigate
                                       class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 -mx-2 hover:bg-[color:var(--nx-hover)] transition-colors">
                                        <span class="text-sm text-[color:var(--nx-text)]">Bescheinigung</span>
                                        <span class="text-xs text-[color:var(--nx-faint)] tabular-nums">{{ optional($c->created_at)->format('d.m.Y') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Letzte Termine — Historie, klickbar --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Letzte Termine</h3>
                    @if($ctxRecent->isEmpty())
                        <div class="text-sm text-[color:var(--nx-muted)]">Keine weiteren Termine.</div>
                    @else
                        <ul class="space-y-1">
                            @foreach($ctxRecent as $a)
                                <li>
                                    <a href="{{ route('encounter.appointments.show', $a->id) }}" wire:navigate
                                       class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 -mx-2 hover:bg-[color:var(--nx-hover)] transition-colors">
                                        <span class="text-sm text-[color:var(--nx-text)] tabular-nums">{{ optional($a->scheduled_at)->format('d.m.Y') }}</span>
                                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $a->status instanceof \Platform\Encounter\Enums\AppointmentStatus ? $a->status->label() : $a->status }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Bescheinigung ausstellen --}}
    <x-nx-modal wire:model="showCertModal" size="md">
        <x-slot name="header">Bescheinigung ausstellen</x-slot>
        <div class="space-y-4">
            <x-nx-input-select name="certAudience" label="Zielgruppe" wire:model="certAudience" :options="$audienceOptions" />
            <x-nx-callout variant="info" icon="heroicon-o-lock-closed" title="Schweigepflicht">
                Für die Zielgruppe „Arbeitgeber" werden medizinische Ergebnisse automatisch ausgelassen.
                Der Inhalt wird zum Ausstellungszeitpunkt eingefroren.
            </x-nx-callout>
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showCertModal', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="issueCertificate">Ausstellen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
