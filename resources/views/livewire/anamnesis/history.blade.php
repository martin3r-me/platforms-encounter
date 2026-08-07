{{--
    Encounter · Anamnese-Verlauf (Stufe C) — versionierte Anamnese über Kontakte als
    Matrix (Fragen × Kontakte); Änderungen ggü. letztem Wert hervorgehoben.
--}}

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Anamnese-Verlauf · ' . ($patient?->getDisplayName() ?? '—')" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Sprechstunde', 'route' => 'encounter.cockpit', 'icon' => 'clipboard-document-list'],
            ['label' => $patient?->getDisplayName() ?? 'Patient'],
            ['label' => 'Anamnese-Verlauf'],
        ]">
            <x-nx-button variant="secondary" size="sm" :href="route('encounter.akte.show', $patient->id)">
                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                <span>Zur Akte</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="full" spacing="space-y-6">
        <x-nx-section icon="heroicon-o-clock" title="Anamnese-Verlauf"
                      description="Jede Spalte = ein Kontakt. Gelb markiert = Wert hat sich gegenüber dem zuletzt bekannten geändert.">
            @if(empty($contacts) || empty($questions))
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-clipboard-document-list">
                        Noch keine erfasste Anamnese für diesen Patienten.
                    </x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm border-collapse">
                            <thead>
                                <tr class="border-b border-[color:var(--nx-line)]">
                                    <th class="text-left font-semibold px-4 py-3 text-[color:var(--nx-text)] sticky left-0 bg-[color:var(--nx-surface)] min-w-[16rem]">
                                        Frage
                                    </th>
                                    @foreach($contacts as $c)
                                        <th class="text-left font-semibold px-4 py-3 text-[color:var(--nx-text)] whitespace-nowrap min-w-[9rem]">
                                            {{ optional($c['date'])->format('d.m.Y') ?? '—' }}
                                            @if($c['occasion'])
                                                <span class="block text-xs font-normal text-[color:var(--nx-faint)]">{{ \Illuminate\Support\Str::limit($c['occasion'], 28) }}</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($questions as $qid => $qtext)
                                    <tr wire:key="q-{{ $qid }}" class="border-b border-[color:var(--nx-line)] last:border-0 align-top">
                                        <td class="px-4 py-3 text-[color:var(--nx-text)] sticky left-0 bg-[color:var(--nx-surface)] min-w-[16rem]">
                                            {{ $qtext }}
                                        </td>
                                        @foreach($contacts as $c)
                                            @php
                                                $val    = $values[$c['id']][$qid] ?? null;
                                                $change = $changes[$c['id']][$qid] ?? null;
                                            @endphp
                                            <td class="px-4 py-3 whitespace-nowrap {{ $change ? 'bg-amber-50 dark:bg-amber-500/10' : '' }}">
                                                @if($val === null || $val === '')
                                                    <span class="text-[color:var(--nx-faint)]">—</span>
                                                @else
                                                    <span class="text-[color:var(--nx-text)]">{{ is_scalar($val) ? $val : json_encode($val) }}</span>
                                                    @if($change && !empty($change['new']))
                                                        <span class="block text-xs text-amber-700 dark:text-amber-400">neu</span>
                                                    @elseif($change)
                                                        <span class="block text-xs text-amber-700 dark:text-amber-400">
                                                            vorher: {{ is_scalar($change['from']) ? $change['from'] : json_encode($change['from']) }}
                                                        </span>
                                                    @endif
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>
</x-ui-page>
