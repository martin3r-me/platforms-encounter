{{--
    Encounter · Termin-Detail/Bearbeiten — nx-Design-System.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Termin · ' . ($appointment->patient?->getDisplayName() ?? '—')" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Termine', 'route' => 'encounter.appointments.index', 'icon' => 'calendar-days'],
            ['label' => optional($appointment->scheduled_at)->format('d.m.Y H:i') ?? 'Termin'],
        ]">
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
        {{-- Termin --}}
        <x-nx-section icon="heroicon-o-calendar-days" title="Termin">
            <x-nx-card>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-nx-input-datetime name="form.scheduled_at" label="Termin" wire:model="form.scheduled_at" />
                    <x-nx-input-select name="form.status" label="Status" wire:model="form.status" :options="$statusOptions" />
                    <x-nx-input-text name="form.performed_by" label="Durchgeführt von" wire:model="form.performed_by" />
                    <x-nx-input-text name="form.doctor_stamp" label="Arztstempel" wire:model="form.doctor_stamp" />
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- Klinischer Freitext (verschlüsselt) --}}
        <x-nx-section icon="heroicon-o-lock-closed" title="Anamnese &amp; Befund"
                      description="Verschlüsselt gespeichert (Schweigepflicht).">
            <x-nx-card>
                <div class="space-y-4">
                    <x-nx-input-textarea name="form.anamnesis" label="Anamnese" wire:model="form.anamnesis" rows="4" />
                    <x-nx-input-textarea name="form.findings" label="Befund" wire:model="form.findings" rows="4" />
                    <x-nx-input-textarea name="form.remarks" label="Bemerkungen" wire:model="form.remarks" rows="3" />
                    <x-nx-input-textarea name="form.confidential" label="Vertraulich" wire:model="form.confidential" rows="3" />
                </div>
            </x-nx-card>
        </x-nx-section>

        {{-- Erbrachte Leistungen --}}
        <x-nx-section icon="heroicon-o-clipboard-document-check" title="Erbrachte Leistungen"
                      :hint="$appointment->services->count()">
            <x-slot name="action">
                <x-nx-button variant="secondary" size="sm" wire:click="$set('showServiceModal', true)">
                    @svg('heroicon-o-plus', 'w-4 h-4') Leistung erfassen
                </x-nx-button>
            </x-slot>
            @if($appointment->services->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-clipboard-document-list">
                        Noch keine Leistungen an diesem Termin.
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
                                    <x-nx-table-cell>{{ $service->title }}</x-nx-table-cell>
                                    <x-nx-table-cell>{{ $service->result ?? '—' }}</x-nx-table-cell>
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
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Patient</h3>
                    @if($appointment->patient)
                        <a href="{{ route('patient.patients.show', $appointment->patient->id) }}" wire:navigate
                           class="text-sm text-[color:var(--nx-accent)] hover:underline">
                            {{ $appointment->patient->getDisplayName() }}
                        </a>
                    @else
                        <div class="text-sm text-[color:var(--nx-muted)]">—</div>
                    @endif
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

    {{-- Leistung erfassen --}}
    <x-nx-modal wire:model="showServiceModal" size="md">
        <x-slot name="header">Leistung erfassen</x-slot>
        <div class="space-y-4">
            <x-nx-input-text name="serviceForm.title" label="Leistung" wire:model="serviceForm.title" required />
            <x-nx-input-text name="serviceForm.result" label="Ergebnis" wire:model="serviceForm.result" />
            <x-nx-input-checkbox name="serviceForm.interval_active" label="Wiedervorlage (Recall)" wire:model.live="serviceForm.interval_active" />
            <x-nx-input-number name="serviceForm.interval_months" label="Intervall (Monate)" wire:model="serviceForm.interval_months"
                               hint="Nächste Fälligkeit = Termin + Intervall." />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showServiceModal', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="addService">Erfassen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

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
