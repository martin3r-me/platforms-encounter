{{--
    Encounter · Bescheinigungen-Liste — nx-Design-System.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Bescheinigungen" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Termine', 'route' => 'encounter.dashboard', 'icon' => 'calendar-days'],
            ['label' => 'Bescheinigungen'],
        ]">
            <x-nx-button variant="secondary" size="sm" :href="route('encounter.appointments.index')" wire:navigate>
                @svg('heroicon-o-calendar-days', 'w-4 h-4')
                <span>Zu den Terminen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @if($certificates->isEmpty())
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-document-check">
                    Noch keine Bescheinigungen. Stelle sie aus einem Termin heraus aus.
                </x-nx-empty>
            </x-nx-card>
        @else
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Titel</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Patient</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Zielgruppe</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Datum</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($certificates as $certificate)
                            <x-nx-table-row wire:key="cert-{{ $certificate->id }}"
                                            :href="route('encounter.certificates.show', $certificate->id)">
                                <x-nx-table-cell>{{ $certificate->title }}</x-nx-table-cell>
                                <x-nx-table-cell>{{ $certificate->patient?->getDisplayName() ?? '—' }}</x-nx-table-cell>
                                <x-nx-table-cell><x-nx-badge>{{ $certificate->audience?->label() }}</x-nx-badge></x-nx-table-cell>
                                <x-nx-table-cell>{{ $certificate->status?->label() }}</x-nx-table-cell>
                                <x-nx-table-cell>{{ optional($certificate->created_at)->format('d.m.Y') }}</x-nx-table-cell>
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
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Bescheinigungen</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">{{ $certificates->count() }} Einträge.</div>
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
</x-ui-page>
