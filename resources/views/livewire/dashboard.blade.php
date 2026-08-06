{{--
    Encounter · Dashboard — nx-Design-System.
    Shell bleibt x-ui-page*, Inhalt ausschließlich x-nx-* + var(--nx-*).
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Termine" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Termine', 'icon' => 'calendar-days'],
        ]">
            <x-nx-button variant="primary" size="sm" :href="route('encounter.appointments.index')" wire:navigate>
                @svg('heroicon-o-calendar-days', 'w-4 h-4')
                <span>Zu den Terminen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-stat-grid :cols="2">
            <a href="{{ route('encounter.appointments.index') }}" wire:navigate>
                <x-nx-stat label="Heute" :value="$stats['today']" icon="heroicon-o-calendar-days" hint="Termine" />
            </a>
            <a href="{{ route('encounter.appointments.index') }}" wire:navigate>
                <x-nx-stat label="Fällig" :value="$stats['due']" icon="heroicon-o-bell-alert"
                           :accent="$stats['due'] > 0 ? 'var(--nx-warning)' : null" hint="Recall" />
            </a>
        </x-nx-stat-grid>

        @if($stats['today'] === 0 && $stats['due'] === 0)
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-calendar-days">
                    Keine Termine heute und keine fälligen Recalls.
                    <x-slot name="action">
                        <x-nx-button variant="secondary" size="sm" :href="route('encounter.appointments.index')" wire:navigate>
                            Zu den Terminen
                        </x-nx-button>
                    </x-slot>
                </x-nx-empty>
            </x-nx-card>
        @endif
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Termine</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">Noch keine Einträge.</div>
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
