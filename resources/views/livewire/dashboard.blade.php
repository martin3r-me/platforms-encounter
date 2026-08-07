{{--
    Encounter · Dashboard = Tages-Agenda (Termine nach Uhrzeit) — nx-Design-System.
--}}
@php
    $weekdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Kalender" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Termine', 'route' => 'encounter.dashboard', 'icon' => 'calendar-days'],
            ['label' => 'Kalender'],
        ]">
            @include('encounter::partials.termine-toggle')
            <div class="flex items-center gap-1">
                <x-nx-button variant="ghost" size="sm" wire:click="goPrev">@svg('heroicon-o-chevron-left', 'w-4 h-4')</x-nx-button>
                <x-nx-button variant="secondary" size="sm" wire:click="goToday">Heute</x-nx-button>
                <x-nx-button variant="ghost" size="sm" wire:click="goNext">@svg('heroicon-o-chevron-right', 'w-4 h-4')</x-nx-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-section icon="heroicon-o-calendar-days"
                      :title="$weekdays[$day->dayOfWeek] . ', ' . $day->format('d.m.Y')"
                      :description="$isToday ? 'Heute' : null"
                      :hint="$appointments->count()">
            @if($appointments->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-calendar-days">
                        Keine Termine an diesem Tag.
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                    @foreach($appointments as $appointment)
                        <a href="{{ route('encounter.appointments.show', $appointment->id) }}" wire:navigate
                           class="flex items-center gap-4 px-4 py-3 hover:bg-[color:var(--nx-hover)]">
                            <span class="w-14 shrink-0 text-sm font-medium text-[color:var(--nx-text)] tabular-nums">
                                {{ optional($appointment->scheduled_at)->format('H:i') }}
                            </span>
                            <span class="flex items-center gap-2 min-w-0 flex-1">
                                @svg('heroicon-o-user', 'w-4 h-4 text-[color:var(--nx-muted)] shrink-0')
                                <span class="truncate text-[color:var(--nx-text)]">{{ $appointment->patient?->getDisplayName() ?? '—' }}</span>
                            </span>
                            <span class="shrink-0">
                                @if($appointment->status)
                                    <x-nx-badge dot>{{ $appointment->status->label() }}</x-nx-badge>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-72" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Tag</h3>
                    <div class="text-sm text-[color:var(--nx-text)]">{{ $weekdays[$day->dayOfWeek] }}, {{ $day->format('d.m.Y') }}</div>
                    <div class="text-sm text-[color:var(--nx-muted)]">{{ $appointments->count() }} Termine</div>
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
