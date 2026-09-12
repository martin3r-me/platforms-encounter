{{-- Encounter · Anamnese-Zeitstrahl (Dauerfakten als Bänder) — nx-Design-System. --}}
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Zeitstrahl · ' . ($patient->getDisplayName() ?? '—')" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_values(array_filter([
            ['label' => 'Sprechstunde', 'route' => 'encounter.cockpit', 'icon' => 'calendar-days'],
            ['label' => $patient->getDisplayName() ?? '—', 'route' => 'encounter.akte.show', 'params' => ['patient' => $patient->id], 'icon' => 'folder-open'],
            ['label' => 'Zeitstrahl'],
        ]))">
            <x-nx-button variant="ghost" size="sm" :href="route('encounter.akte.show', $patient->id)" wire:navigate>
                @svg('heroicon-o-folder-open', 'w-4 h-4')
                <span>Zur Akte</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-section icon="heroicon-o-chart-bar" title="Dauerfakten (Zeitstrahl)"
                      description="Patientenweite Dauerzustände über die Zeit. Laufende sind farbig, beendete blass.">
            <x-nx-card>
                @if(empty($rows))
                    <x-nx-empty icon="heroicon-o-chart-bar">
                        Noch keine Dauerfakten erfasst. Sie entstehen, wenn am Termin Fragen der Art „Dauerzustand" beantwortet werden.
                    </x-nx-empty>
                @else
                    {{-- Jahresachse --}}
                    <div class="relative h-5 mb-3 ml-48 border-b border-[color:var(--nx-line)]">
                        @foreach($years as $yr)
                            <span class="absolute -translate-x-1/2 text-xs text-[color:var(--nx-faint)] tabular-nums" style="left: {{ $yr['offset'] }}%">{{ $yr['label'] }}</span>
                        @endforeach
                    </div>

                    <div class="space-y-3">
                        @foreach($rows as $row)
                            <div class="flex items-center gap-3">
                                <div class="w-48 shrink-0 text-sm text-[color:var(--nx-text)] truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</div>
                                <div class="relative flex-1 h-6">
                                    @foreach($row['bands'] as $b)
                                        <div class="absolute top-0 h-6 rounded flex items-center overflow-hidden {{ $b['open'] ? 'bg-[color:var(--nx-accent)] text-white' : 'bg-[color:var(--nx-hover)] border border-[color:var(--nx-line)] text-[color:var(--nx-muted)]' }}"
                                             style="left: {{ $b['offset'] }}%; width: {{ $b['width'] }}%"
                                             title="{{ $b['value'] }} · {{ $b['from'] }}–{{ $b['until'] ?? 'laufend' }}">
                                            <span class="px-2 text-xs truncate">{{ $b['value'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nx-card>
        </x-nx-section>
    </x-ui-page-container>
</x-ui-page>
