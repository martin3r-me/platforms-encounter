{{--
    Encounter · Akte (Verlauf) — Journal-Timeline, neueste zuerst.
    Innere Sidebar = Verlauf-Marken (Sprung), rechts = Werte & Status.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Akte · ' . ($patient->getDisplayName() ?? '—')" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Akte', 'route' => 'encounter.dashboard', 'icon' => 'folder-open'],
            ['label' => $patient->getDisplayName() ?? '—'],
        ]">
            <x-nx-button variant="secondary" size="sm" :href="route('patient.patients.show', $patient->id)" wire:navigate>
                @svg('heroicon-o-identification', 'w-4 h-4')
                <span>Stammdaten</span>
            </x-nx-button>
            <x-nx-button variant="primary" size="sm" :href="route('encounter.appointments.index')" wire:navigate>
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Termin</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-4">
        @if(empty($entries))
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-folder-open">
                    Noch kein Verlauf. Termine, Vorsorgen und Beschäftigung erscheinen hier chronologisch.
                </x-nx-empty>
            </x-nx-card>
        @else
            @foreach($entries as $entry)
                <div id="{{ $entry['anchor'] }}" class="scroll-mt-24" wire:key="{{ $entry['anchor'] }}">
                    <x-nx-card>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 shrink-0 text-[color:var(--nx-muted)]">
                                @svg($entry['icon'] ?? 'heroicon-o-clock', 'w-5 h-5')
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-3">
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $entry['title'] }}</span>
                                        @if(!empty($entry['subtitle']))
                                            <span class="ml-2 text-xs text-[color:var(--nx-muted)]">{{ $entry['subtitle'] }}</span>
                                        @endif
                                    </div>
                                    <span class="shrink-0 text-xs text-[color:var(--nx-faint)] tabular-nums">
                                        {{ $entry['date']->format('d.m.Y') }}@if($entry['type'] === 'appointment') · {{ $entry['date']->format('H:i') }}@endif
                                    </span>
                                </div>

                                @if(!empty($entry['badge']))
                                    <div class="mt-1">
                                        <x-nx-badge :variant="$entry['badge']['variant'] ?? 'default'" dot>{{ $entry['badge']['label'] }}</x-nx-badge>
                                    </div>
                                @endif

                                @if(!empty($entry['lines']))
                                    <ul class="mt-2 space-y-1">
                                        @foreach($entry['lines'] as $line)
                                            <li class="text-sm text-[color:var(--nx-text)]">{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if(!empty($entry['url']))
                                    <div class="mt-2">
                                        <a href="{{ $entry['url'] }}" wire:navigate class="inline-flex items-center gap-1 text-xs text-[color:var(--nx-accent)] hover:underline">
                                            @svg('heroicon-o-arrow-up-right', 'w-3.5 h-3.5') Öffnen
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-nx-card>
                </div>
            @endforeach
        @endif
    </x-ui-page-container>

    {{-- Innere Sidebar: Verlauf-Marken (Sprung-Navigation) --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Verlauf" icon="heroicon-o-bars-3-bottom-left" width="w-64" :defaultOpen="true">
            <nav class="p-2 space-y-0.5">
                @forelse($entries as $entry)
                    <a href="#{{ $entry['anchor'] }}"
                       class="flex items-center gap-2 px-2 py-1.5 rounded-md text-sm text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]">
                        <span class="w-16 shrink-0 text-xs text-[color:var(--nx-faint)] tabular-nums">{{ $entry['date']->format('d.m.y') }}</span>
                        @svg($entry['icon'] ?? 'heroicon-o-clock', 'w-4 h-4 text-[color:var(--nx-muted)] shrink-0')
                        <span class="truncate">{{ $entry['title'] }}</span>
                    </a>
                @empty
                    <div class="px-2 py-3 text-sm text-[color:var(--nx-muted)]">Kein Verlauf.</div>
                @endforelse
            </nav>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar: Werte & Status --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Werte &amp; Status" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-2">Fällige Vorsorgen</h3>
                    @if(empty($dueProvisions))
                        <div class="text-sm text-[color:var(--nx-muted)]">Keine fälligen Vorsorgen.</div>
                    @else
                        <ul class="space-y-2">
                            @foreach($dueProvisions as $p)
                                <li class="flex items-start justify-between gap-2">
                                    <span class="text-sm text-[color:var(--nx-text)] min-w-0 truncate">{{ $p['title'] }}</span>
                                    <x-nx-badge :variant="$p['badge']['variant'] ?? 'default'" dot>{{ $p['badge']['label'] }}</x-nx-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-2">Laborwerte</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">
                        Kommt: Labor-Layer (eigenes Modul, an patient angedockt) — Werte erscheinen hier als stehender Überblick + im Verlauf.
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
