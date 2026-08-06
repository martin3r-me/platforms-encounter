{{--
    Encounter · Termin-Liste — nx-Design-System.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Termine" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Termine', 'route' => 'encounter.dashboard', 'icon' => 'calendar-days'],
            ['label' => 'Liste'],
        ]">
            <x-nx-button variant="primary" size="sm" wire:click="$set('showCreate', true)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Termin</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @if($appointments->isEmpty())
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-calendar-days">
                    Noch keine Termine. Lege den ersten über „Neuer Termin" an.
                </x-nx-empty>
            </x-nx-card>
        @else
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Patient</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Termin</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($appointments as $appointment)
                            <x-nx-table-row wire:key="appt-{{ $appointment->id }}"
                                            :href="route('encounter.appointments.show', $appointment->id)">
                                <x-nx-table-cell>{{ $appointment->patient?->getDisplayName() ?? '—' }}</x-nx-table-cell>
                                <x-nx-table-cell>{{ optional($appointment->scheduled_at)->format('d.m.Y H:i') }}</x-nx-table-cell>
                                <x-nx-table-cell>
                                    <x-nx-badge>{{ $appointment->status?->label() ?? '—' }}</x-nx-badge>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        @endif
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Termine</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">{{ $appointments->count() }} Einträge.</div>
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

    {{-- Anlegen-Modal --}}
    <x-nx-modal wire:model="showCreate" size="md">
        <x-slot name="header">Neuer Termin</x-slot>
        <div class="space-y-4">
            <x-nx-input-select name="patient_id" label="Patient" wire:model="patient_id" required>
                <option value="">— Patient wählen —</option>
                @foreach($patients as $patient)
                    <option value="{{ $patient->id }}">{{ $patient->getDisplayName() }}</option>
                @endforeach
            </x-nx-input-select>
            <x-nx-input-datetime name="scheduled_at" label="Termin" wire:model="scheduled_at" required />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showCreate', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="create">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
