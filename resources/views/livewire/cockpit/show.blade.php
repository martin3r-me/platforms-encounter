{{--
    Encounter · Sprechstunde-Cockpit — EINE ärztliche Oberfläche.
    Innere Sidebar = Tages-Agenda (Termin wählen), Mitte = Akte des gewählten Patienten,
    rechts = Werte & Status. Der Arzt bleibt in einer View.
--}}
@php $weekdays = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag']; @endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Sprechstunde" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_values(array_filter([
            ['label' => 'Sprechstunde', 'route' => 'encounter.cockpit', 'icon' => 'calendar-days'],
            $patient ? ['label' => $patient->getDisplayName() ?? '—'] : null,
        ]))">
            @unless($isToday)
                <x-nx-button variant="secondary" size="sm" wire:click="goToday">
                    @svg('heroicon-o-calendar-days', 'w-4 h-4') Heute
                </x-nx-button>
            @endunless
            @if($patient)
                <x-nx-button variant="secondary" size="sm" :href="route('patient.patients.show', $patient->id)" wire:navigate>
                    @svg('heroicon-o-identification', 'w-4 h-4')
                    <span>Stammdaten</span>
                </x-nx-button>
                <x-nx-button variant="primary" size="sm" :href="route('encounter.akte.show', $patient->id)" wire:navigate>
                    @svg('heroicon-o-folder-open', 'w-4 h-4')
                    <span>Volle Akte</span>
                </x-nx-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Mitte: Akte des gewählten Patienten (oder Aufforderung) --}}
    <x-ui-page-container width="contained" spacing="space-y-6">
        @if(!$patient)
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-cursor-arrow-rays">
                    Wähle links einen Termin, um die Akte des Patienten zu öffnen — Verlauf, Vorsorge und Bescheinigungen erscheinen hier.
                </x-nx-empty>
            </x-nx-card>
        @else
            {{-- Patienten-Kopf + Tabs --}}
            <x-nx-card>
                <div class="flex items-center gap-3">
                    @svg('heroicon-o-user-circle', 'w-8 h-8 text-[color:var(--nx-muted)]')
                    <div class="min-w-0">
                        <div class="text-base font-semibold text-[color:var(--nx-text)]">{{ $patient->getDisplayName() ?? '—' }}</div>
                        <div class="text-xs text-[color:var(--nx-muted)]">
                            @if($patient->birth_date)geb. {{ \Illuminate\Support\Carbon::parse($patient->birth_date)->format('d.m.Y') }}@endif
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-1 border-t border-[color:var(--nx-line)] pt-3">
                    <button type="button" wire:click="$set('tab', 'verlauf')"
                            @class([
                                'px-3 py-1.5 rounded-md text-sm transition-colors',
                                'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' => $tab === 'verlauf',
                                'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' => $tab !== 'verlauf',
                            ])>Verlauf</button>
                    <button type="button" wire:click="$set('tab', 'stammdaten')"
                            @class([
                                'px-3 py-1.5 rounded-md text-sm transition-colors',
                                'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' => $tab === 'stammdaten',
                                'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' => $tab !== 'stammdaten',
                            ])>Stammdaten</button>
                </div>
            </x-nx-card>

            @if($tab === 'verlauf' && $selectedAppointmentId)
                {{-- Bescheinigung direkt aus dem gewählten Termin ausstellen --}}
                <x-nx-card>
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2 text-sm text-[color:var(--nx-muted)]">
                            @svg('heroicon-o-document-check', 'w-4 h-4')
                            <span>Bescheinigung aus diesem Termin ausstellen</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-nx-button variant="secondary" size="sm" wire:click="issueCertificate('patient')"
                                         wire:confirm="Vorsorgebescheinigung (Patient) ausstellen?">Patient</x-nx-button>
                            <x-nx-button variant="secondary" size="sm" wire:click="issueCertificate('employer')"
                                         wire:confirm="Vorsorgebescheinigung (Arbeitgeber) ausstellen?">Arbeitgeber</x-nx-button>
                        </div>
                    </div>
                </x-nx-card>
            @endif

            @if($tab === 'stammdaten')
                {{-- Stammdaten (read-only, Bearbeiten im Patienten-Modul) --}}
                <x-nx-card>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)]">Identität</h3>
                        <a href="{{ route('patient.patients.show', $patient->id) }}" wire:navigate
                           class="inline-flex items-center gap-1 text-xs text-[color:var(--nx-accent)] hover:underline">
                            @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5') Bearbeiten
                        </a>
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
                        <div>
                            <div class="text-xs text-[color:var(--nx-muted)] mb-1">Telefon</div>
                            @forelse($patient->phoneNumbers as $ph)
                                <div class="text-[color:var(--nx-text)]">{{ $ph->number }} @if($ph->is_primary)<span class="text-xs text-[color:var(--nx-faint)]">· primär</span>@endif</div>
                            @empty
                                <div class="text-[color:var(--nx-muted)]">—</div>
                            @endforelse
                        </div>
                        <div>
                            <div class="text-xs text-[color:var(--nx-muted)] mb-1">E-Mail</div>
                            @forelse($patient->emailAddresses as $em)
                                <div class="text-[color:var(--nx-text)]">{{ $em->email }} @if($em->is_primary)<span class="text-xs text-[color:var(--nx-faint)]">· primär</span>@endif</div>
                            @empty
                                <div class="text-[color:var(--nx-muted)]">—</div>
                            @endforelse
                        </div>
                        <div>
                            <div class="text-xs text-[color:var(--nx-muted)] mb-1">Adresse</div>
                            @forelse($patient->postalAddresses as $ad)
                                <div class="text-[color:var(--nx-text)]">{{ trim(($ad->street ?? '').' '.($ad->house_number ?? '')) }}, {{ trim(($ad->postal_code ?? '').' '.($ad->city ?? '')) }}</div>
                            @empty
                                <div class="text-[color:var(--nx-muted)]">—</div>
                            @endforelse
                        </div>
                    </div>
                </x-nx-card>
            @elseif(empty($grouped))
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-folder-open">
                        Noch kein Verlauf. Termine, Vorsorgen und Beschäftigung erscheinen hier chronologisch.
                    </x-nx-empty>
                </x-nx-card>
            @else
                <div x-data="{}" x-init="
                        const marks = {};
                        document.querySelectorAll('[data-mark]').forEach(m => marks[m.dataset.mark] = m);
                        const obs = new IntersectionObserver((es) => {
                            es.forEach(e => {
                                const m = marks[e.target.dataset.journalAnchor];
                                if (m) m.style.backgroundColor = e.isIntersecting ? 'var(--nx-active)' : '';
                            });
                        }, { rootMargin: '-12% 0px -78% 0px' });
                        $nextTick(() => document.querySelectorAll('[data-journal-anchor]').forEach(el => obs.observe(el)));
                    " class="space-y-6" wire:key="journal-{{ $patient->id }}">
                    @foreach($grouped as $dateKey => $dayEntries)
                        @php $d = \Illuminate\Support\Carbon::parse($dateKey); @endphp
                        <div>
                            <div class="sticky top-0 z-10 mb-2 py-1 bg-[color:var(--nx-bg)]">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)]">
                                    {{ $weekdays[$d->dayOfWeek] }}, {{ $d->format('d.m.Y') }}
                                </h3>
                            </div>
                            <div class="space-y-3">
                                @foreach($dayEntries as $entry)
                                    <div id="{{ $entry['anchor'] }}" data-journal-anchor="{{ $entry['anchor'] }}" class="scroll-mt-24" wire:key="{{ $entry['anchor'] }}">
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
                                                            @if(($entry['type'] ?? null) === 'appointment'){{ $entry['date']->format('H:i') }}@endif
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
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </x-ui-page-container>

    {{-- Innere Sidebar: Tages-Navigator (mit Counts) + Agenda des gewählten Tages --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Agenda" icon="heroicon-o-calendar-days" width="w-80" :defaultOpen="true">
            {{-- Tages-Navigator: kommende Arbeitstage mit Anzahl meiner Termine --}}
            <div class="px-2 pt-2 pb-2 space-y-px border-b border-[color:var(--nx-line)]">
                @foreach($dayStrip as $d)
                    <button type="button" wire:click="selectDay('{{ $d['date'] }}')" wire:key="day-{{ $d['date'] }}"
                            @class([
                                'w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-left transition-colors',
                                'bg-[color:var(--nx-active)]' => $d['isActive'],
                                'hover:bg-[color:var(--nx-hover)]' => !$d['isActive'],
                            ])>
                        <span @class([
                            'w-7 shrink-0 text-xs font-semibold',
                            'text-[color:var(--nx-accent)]' => $d['isToday'],
                            'text-[color:var(--nx-muted)]' => !$d['isToday'],
                        ])>{{ $d['weekday'] }}</span>
                        <span @class([
                            'flex-1 text-sm tabular-nums',
                            'font-semibold text-[color:var(--nx-text)]' => $d['isActive'],
                            'text-[color:var(--nx-text)]' => !$d['isActive'],
                        ])>{{ $d['day'] }}@if($d['isToday'])<span class="text-xs text-[color:var(--nx-faint)]"> · heute</span>@endif</span>
                        @if($d['count'] > 0)
                            <span class="shrink-0 min-w-[1.25rem] text-center text-[11px] leading-none px-1.5 py-0.5 rounded-full {{ $d['isActive'] ? 'bg-[color:var(--nx-surface)]' : 'bg-[color:var(--nx-bg)]' }} text-[color:var(--nx-muted)]">{{ $d['count'] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="p-3">
                {{-- Meine / Alle (Default: nur meine) --}}
                @if($hasDoctors)
                    <div class="flex items-center justify-between px-1 pb-2">
                        <div class="inline-flex rounded-md border border-[color:var(--nx-line)] overflow-hidden text-xs">
                            <button type="button" wire:click="$set('doc','mine')"
                                    @class(['px-2.5 py-1', 'bg-[color:var(--nx-active)] font-semibold text-[color:var(--nx-text)]' => $doc !== 'all', 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' => $doc === 'all'])>Meine</button>
                            <button type="button" wire:click="$set('doc','all')"
                                    @class(['px-2.5 py-1 border-l border-[color:var(--nx-line)]', 'bg-[color:var(--nx-active)] font-semibold text-[color:var(--nx-text)]' => $doc === 'all', 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' => $doc !== 'all'])>Alle</button>
                        </div>
                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $appointments->count() }} {{ $appointments->count() === 1 ? 'Termin' : 'Termine' }}</span>
                    </div>
                    @if($doc !== 'all' && !$hasMyDoctor)
                        <div class="px-1 pb-2 text-xs text-[color:var(--nx-faint)]">Kein Arzt mit dir verknüpft — zeige alle. (Praxis → Ärzte → „Das bin ich")</div>
                    @endif
                @endif

                @if($appointments->isEmpty())
                    <div class="px-1 py-6 text-center text-sm text-[color:var(--nx-muted)]">Keine Termine an diesem Tag.</div>
                @else
                    <div class="space-y-0.5">
                        @foreach($appointments as $appointment)
                            @php $lt = $appointment->location_type; $docId = $appointment->doctor_entity_id; @endphp
                            <button type="button" wire:click="selectAppointment({{ $appointment->id }})" wire:key="appt-{{ $appointment->id }}"
                                    @class([
                                        'w-full flex items-center gap-2 px-2 py-2 rounded-md text-left transition-colors',
                                        'hover:bg-[color:var(--nx-hover)]' => $selectedAppointmentId !== $appointment->id,
                                        'bg-[color:var(--nx-active)]' => $selectedAppointmentId === $appointment->id,
                                    ])>
                                <span class="w-1.5 h-8 shrink-0 rounded-full" style="background: {{ $docId ? \Platform\Encounter\Support\Doctors::color((int)$docId) : 'transparent' }}"></span>
                                <span class="w-11 shrink-0 text-sm font-medium text-[color:var(--nx-text)] tabular-nums">
                                    {{ optional($appointment->scheduled_at)->format('H:i') }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-[color:var(--nx-text)]">{{ $appointment->patient?->getDisplayName() ?? '—' }}</span>
                                    <span class="flex items-center gap-1.5 mt-0.5">
                                        @if($lt)
                                            <span class="inline-flex items-center gap-1 text-xs" style="color: {{ $lt->color() }}">
                                                @svg($lt->icon(), 'w-3.5 h-3.5') {{ $lt->label() }}
                                            </span>
                                        @endif
                                        @if($doc === 'all' && $docId && !empty($doctorLabels[$docId]))
                                            <span class="text-xs text-[color:var(--nx-faint)] truncate">· {{ $doctorLabels[$docId] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar: Werte & Status --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Werte & Status" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-6">
                @if($patient)
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
                @else
                    <div class="text-sm text-[color:var(--nx-muted)]">Wähle einen Termin, um Werte & Status zu sehen.</div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
