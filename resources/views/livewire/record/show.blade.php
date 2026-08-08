{{--
    Encounter · Patientenakte — die EINE Personen-Sicht (Tabs komponieren die Fachmodule).
    Verlauf (Timeline) · Stammdaten · Betriebsärztlich · Bescheinigungen. Rechts: Werte & Status.
--}}
@php $weekdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag']; @endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Akte · ' . ($patient->getDisplayName() ?? '—')" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Akte', 'route' => 'encounter.cockpit', 'icon' => 'folder-open'],
            ['label' => $patient->getDisplayName() ?? '—'],
        ]">
            <x-nx-button variant="secondary" size="sm" :href="route('patient.patients.show', $patient->id)" wire:navigate>
                @svg('heroicon-o-pencil-square', 'w-4 h-4')
                <span>Stammdaten bearbeiten</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        {{-- Patienten-Kopf + Tabs --}}
        <x-nx-card>
            <div class="flex items-center gap-3">
                @svg('heroicon-o-user-circle', 'w-9 h-9 text-[color:var(--nx-muted)]')
                <div class="min-w-0 flex-1">
                    <div class="text-base font-semibold text-[color:var(--nx-text)]">{{ $patient->getDisplayName() ?? '—' }}</div>
                    <div class="text-xs text-[color:var(--nx-muted)]">
                        @if($patient->birth_date)geb. {{ \Illuminate\Support\Carbon::parse($patient->birth_date)->format('d.m.Y') }}@endif
                        @php($emp = $employments->firstWhere('active', true) ?? $employments->first())
                        @if($emp && $emp->organizationEntity)
                            · {{ $emp->position ? $emp->position.' @ ' : '' }}{{ $emp->organizationEntity->name }}
                        @endif
                    </div>
                </div>
            </div>

            @php
                $tabs = [
                    'verlauf'          => ['Verlauf', $entries ? count($entries) : 0],
                    'stammdaten'       => ['Stammdaten', null],
                    'betriebsaerztlich'=> ['Betriebsärztlich', $provisions->count() + $employments->count()],
                    'bescheinigungen'  => ['Bescheinigungen', $certificates->count()],
                ];
            @endphp
            <div class="mt-3 flex items-center gap-1 border-t border-[color:var(--nx-line)] pt-3 overflow-x-auto">
                @foreach($tabs as $key => $t)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')"
                            @class([
                                'px-3 py-1.5 rounded-md text-sm transition-colors whitespace-nowrap',
                                'bg-[color:var(--nx-active)] text-[color:var(--nx-text)] font-medium' => $tab === $key,
                                'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' => $tab !== $key,
                            ])>
                        {{ $t[0] }}@if($t[1] !== null)<span class="ml-1.5 text-xs text-[color:var(--nx-faint)]">{{ $t[1] }}</span>@endif
                    </button>
                @endforeach
            </div>
        </x-nx-card>

        {{-- TAB: Verlauf --}}
        @if($tab === 'verlauf')
            @if(empty($grouped))
                <x-nx-card><x-nx-empty icon="heroicon-o-folder-open">Noch kein Verlauf. Termine, Vorsorgen und Beschäftigung erscheinen hier chronologisch.</x-nx-empty></x-nx-card>
            @else
                <div class="space-y-6">
                    @foreach($grouped as $dateKey => $dayEntries)
                        @php $d = \Illuminate\Support\Carbon::parse($dateKey); @endphp
                        <div>
                            <div class="sticky top-0 z-10 mb-2 py-1 bg-[color:var(--nx-bg)]">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $weekdays[$d->dayOfWeek] }}, {{ $d->format('d.m.Y') }}</h3>
                            </div>
                            <div class="space-y-3">
                                @foreach($dayEntries as $entry)
                                    <x-nx-card wire:key="{{ $entry['anchor'] }}">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 shrink-0 text-[color:var(--nx-muted)]">@svg($entry['icon'] ?? 'heroicon-o-clock', 'w-5 h-5')</div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-baseline justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $entry['title'] }}</span>
                                                        @if(!empty($entry['subtitle']))<span class="ml-2 text-xs text-[color:var(--nx-muted)]">{{ $entry['subtitle'] }}</span>@endif
                                                    </div>
                                                    <span class="shrink-0 text-xs text-[color:var(--nx-faint)] tabular-nums">@if($entry['type'] === 'appointment'){{ $entry['date']->format('H:i') }}@endif</span>
                                                </div>
                                                @if(!empty($entry['badge']))<div class="mt-1"><x-nx-badge :variant="$entry['badge']['variant'] ?? 'default'" dot>{{ $entry['badge']['label'] }}</x-nx-badge></div>@endif
                                                @if(!empty($entry['lines']))<ul class="mt-2 space-y-1">@foreach($entry['lines'] as $line)<li class="text-sm text-[color:var(--nx-text)]">{{ $line }}</li>@endforeach</ul>@endif
                                                @if(!empty($entry['url']))<div class="mt-2"><a href="{{ $entry['url'] }}" wire:navigate class="inline-flex items-center gap-1 text-xs text-[color:var(--nx-accent)] hover:underline">@svg('heroicon-o-arrow-up-right', 'w-3.5 h-3.5') Öffnen</a></div>@endif
                                            </div>
                                        </div>
                                    </x-nx-card>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- TAB: Stammdaten (read-only, Bearbeiten im Patienten-Modul) --}}
        @if($tab === 'stammdaten')
            <x-nx-card>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)]">Identität</h3>
                    <a href="{{ route('patient.patients.show', $patient->id) }}" wire:navigate class="inline-flex items-center gap-1 text-xs text-[color:var(--nx-accent)] hover:underline">@svg('heroicon-o-pencil-square', 'w-3.5 h-3.5') Bearbeiten</a>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-[color:var(--nx-muted)]">Name</dt><dd class="text-[color:var(--nx-text)]">{{ $patient->getDisplayName() ?? '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Geburtsdatum</dt><dd class="text-[color:var(--nx-text)]">{{ $patient->birth_date ? \Illuminate\Support\Carbon::parse($patient->birth_date)->format('d.m.Y') : '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Geschlecht</dt><dd class="text-[color:var(--nx-text)]">{{ $patient->gender ?: '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Nationalität</dt><dd class="text-[color:var(--nx-text)]">{{ $patient->nationality ?: '—' }}</dd></div>
                </dl>
            </x-nx-card>
            <x-nx-card>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Kontakt</h3>
                <div class="space-y-3 text-sm">
                    <div><div class="text-xs text-[color:var(--nx-muted)] mb-1">Telefon</div>
                        @forelse($patient->phoneNumbers as $ph)<div class="text-[color:var(--nx-text)]">{{ $ph->number }}</div>@empty<div class="text-[color:var(--nx-muted)]">—</div>@endforelse</div>
                    <div><div class="text-xs text-[color:var(--nx-muted)] mb-1">E-Mail</div>
                        @forelse($patient->emailAddresses as $em)<div class="text-[color:var(--nx-text)]">{{ $em->email }}</div>@empty<div class="text-[color:var(--nx-muted)]">—</div>@endforelse</div>
                    <div><div class="text-xs text-[color:var(--nx-muted)] mb-1">Adresse</div>
                        @forelse($patient->postalAddresses as $ad)<div class="text-[color:var(--nx-text)]">{{ trim(($ad->street ?? '').' '.($ad->house_number ?? '')) }}, {{ trim(($ad->postal_code ?? '').' '.($ad->city ?? '')) }}</div>@empty<div class="text-[color:var(--nx-muted)]">—</div>@endforelse</div>
                </div>
            </x-nx-card>
        @endif

        {{-- TAB: Betriebsärztlich (occupational) --}}
        @if($tab === 'betriebsaerztlich')
            <x-nx-section icon="heroicon-o-briefcase" title="Beschäftigung">
                @if($employments->isEmpty())
                    <x-nx-card><x-nx-empty icon="heroicon-o-briefcase">Keine Beschäftigung hinterlegt.</x-nx-empty></x-nx-card>
                @else
                    <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($employments as $emp)
                            <div class="px-4 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm text-[color:var(--nx-text)]">{{ $emp->position ?: 'Beschäftigung' }} @ {{ $emp->organizationEntity?->name ?? '—' }}</div>
                                    <div class="text-xs text-[color:var(--nx-muted)]">{{ optional($emp->started_at)->format('d.m.Y') ?? '—' }} – {{ optional($emp->ended_at)->format('d.m.Y') ?? 'offen' }}</div>
                                </div>
                                <x-nx-badge :variant="$emp->active ? 'success' : 'neutral'" dot>{{ $emp->active ? 'aktiv' : 'beendet' }}</x-nx-badge>
                            </div>
                        @endforeach
                    </x-nx-card>
                @endif
            </x-nx-section>

            <x-nx-section icon="heroicon-o-shield-check" title="Vorsorge" :hint="$provisions->count()">
                @if($provisions->isEmpty())
                    <x-nx-card><x-nx-empty icon="heroicon-o-shield-check">Keine Vorsorge hinterlegt. In Arbeitsmedizin aus der GBU ableiten.</x-nx-empty></x-nx-card>
                @else
                    <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($provisions as $p)
                            <div class="px-4 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm text-[color:var(--nx-text)] truncate">{{ optional($p->occasion)->title ?? 'Vorsorge' }}</div>
                                    <div class="text-xs text-[color:var(--nx-muted)]">{{ $p->type?->label() ?? '' }}</div>
                                </div>
                                <div class="shrink-0 text-right">
                                    @if($p->next_due_at)
                                        <x-nx-badge :variant="$p->isOverdue() ? 'danger' : 'default'" dot>fällig {{ $p->next_due_at->format('d.m.Y') }}</x-nx-badge>
                                    @else
                                        <span class="text-xs text-[color:var(--nx-faint)]">—</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-nx-card>
                @endif
            </x-nx-section>
        @endif

        {{-- TAB: Bescheinigungen (encounter) --}}
        @if($tab === 'bescheinigungen')
            @if($certificates->isEmpty())
                <x-nx-card><x-nx-empty icon="heroicon-o-document-check">Noch keine Bescheinigung. Aus einem Termin ausstellen.</x-nx-empty></x-nx-card>
            @else
                <x-nx-card flush class="divide-y divide-[color:var(--nx-line)]">
                    @foreach($certificates as $c)
                        <x-nx-list-item :href="route('encounter.certificates.show', $c->id)" wire:navigate
                                        icon="heroicon-o-document-check"
                                        :title="$c->title"
                                        :subtitle="$c->audience?->label()"
                                        :meta="optional($c->created_at)->format('d.m.Y')" />
                    @endforeach
                </x-nx-card>
            @endif
        @endif
    </x-ui-page-container>

    {{-- Rechte Sidebar: Werte & Status --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Werte & Status" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
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
                    <div class="text-sm text-[color:var(--nx-muted)]">Kommt: Labor-Layer — Werte erscheinen hier als Überblick + im Verlauf.</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
