{{--
    Encounter · Bescheinigung-Detail (eingefroren) — nx-Design-System.
--}}
@php($content = $certificate->content ?? [])
@php($lh = $content['letterhead'] ?? $letterhead)
@php($isEmployer = $certificate->audience === \Platform\Encounter\Enums\Audience::Employer)

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$certificate->title" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Bescheinigungen', 'route' => 'encounter.certificates.index', 'icon' => 'document-check'],
            ['label' => $certificate->title],
        ]">
            <x-nx-button variant="secondary" size="sm" wire:click="$set('showDeliveryModal', true)">
                @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                <span>Zustellung erfassen</span>
            </x-nx-button>
            @if($certificate->status !== \Platform\Encounter\Enums\CertificateStatus::Revoked)
                <x-nx-button variant="danger" size="sm" wire:click="revoke"
                             wire:confirm="Bescheinigung wirklich widerrufen?">
                    @svg('heroicon-o-x-circle', 'w-4 h-4')
                    <span>Widerrufen</span>
                </x-nx-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">
        @if($certificate->status === \Platform\Encounter\Enums\CertificateStatus::Revoked)
            <x-nx-callout variant="danger" icon="heroicon-o-x-circle" title="Widerrufen">
                Diese Bescheinigung wurde widerrufen.
            </x-nx-callout>
        @endif

        {{-- Briefkopf (eingefroren aus content; sonst live aus practice/encounter) --}}
        @if($lh)
            <x-nx-card>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-base font-semibold text-[color:var(--nx-text)]">{{ $lh['name'] ?? 'Praxis' }}</div>
                        @foreach(($lh['address_lines'] ?? []) as $line)
                            <div class="text-sm text-[color:var(--nx-muted)]">{{ $line }}</div>
                        @endforeach
                        @if(!empty($lh['contact_lines']))
                            <div class="mt-1 text-xs text-[color:var(--nx-faint)]">{{ implode(' · ', $lh['contact_lines']) }}</div>
                        @endif
                        @if(!empty($lh['bsnr']))
                            <div class="mt-1 text-xs text-[color:var(--nx-faint)]">BSNR {{ $lh['bsnr'] }}</div>
                        @endif
                    </div>
                    @if(!empty($lh['logo_url']))
                        <img src="{{ $lh['logo_url'] }}" alt="Logo" class="h-14 max-w-[160px] object-contain shrink-0" />
                    @endif
                </div>

                @if(!empty($lh['doctor']))
                    @php($doc = $lh['doctor'])
                    <div class="mt-4 pt-4 border-t border-[color:var(--nx-line)] flex items-end justify-between gap-4">
                        <div class="text-sm">
                            <div class="text-[color:var(--nx-text)]">{{ trim(($doc['title'] ?? '') . ' ' . ($doc['name'] ?? '')) ?: 'Ausstellender Arzt' }}</div>
                            @if(!empty($doc['specialty']))
                                <div class="text-[color:var(--nx-muted)]">{{ $doc['specialty'] }}</div>
                            @endif
                            @if(!empty($doc['lanr']))
                                <div class="text-xs text-[color:var(--nx-faint)]">LANR {{ $doc['lanr'] }}</div>
                            @endif
                        </div>
                        <div class="flex items-end gap-4 shrink-0">
                            @if(!empty($doc['signature_url']))
                                <img src="{{ $doc['signature_url'] }}" alt="Unterschrift" class="h-12 max-w-[160px] object-contain" />
                            @endif
                            @if(!empty($lh['stamp_url']))
                                <img src="{{ $lh['stamp_url'] }}" alt="Stempel" class="h-16 max-w-[120px] object-contain" />
                            @endif
                        </div>
                    </div>
                @endif
            </x-nx-card>
        @endif

        {{-- Vorsorgebescheinigung nach AMR 6.3 --}}
        @php($person = $content['person'] ?? ['name' => $content['patient'] ?? $certificate->patient?->getDisplayName()])
        @php($employer = $content['employer'] ?? null)
        @php($occasion = $content['occasion'] ?? [])
        <x-nx-section icon="heroicon-o-document-check" title="Vorsorgebescheinigung"
                      description="Pflichtfelder nach AMR 6.3.">
            <x-nx-card>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-[color:var(--nx-muted)]">Beschäftigte:r</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ $person['name'] ?? '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Geburtsdatum</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ !empty($person['birth_date']) ? \Illuminate\Support\Carbon::parse($person['birth_date'])->format('d.m.Y') : '—' }}</dd></div>

                    <div><dt class="text-[color:var(--nx-muted)]">Arbeitgeber</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ $employer['name'] ?? '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Anlass der Vorsorge</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ $occasion['title'] ?? '—' }}</dd></div>

                    <div><dt class="text-[color:var(--nx-muted)]">Art der Vorsorge</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ $occasion['care_type'] ?? '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Zielgruppe</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ $certificate->audience?->label() }}</dd></div>

                    <div><dt class="text-[color:var(--nx-muted)]">Datum der Vorsorge</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ !empty($content['examined_on']) ? \Illuminate\Support\Carbon::parse($content['examined_on'])->format('d.m.Y') : '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Nächste Vorsorge (spätestens)</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ !empty($content['next_due']) ? \Illuminate\Support\Carbon::parse($content['next_due'])->format('d.m.Y') : '—' }}</dd></div>

                    <div><dt class="text-[color:var(--nx-muted)]">Ausgestellt am</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ !empty($content['issued_on']) ? \Illuminate\Support\Carbon::parse($content['issued_on'])->format('d.m.Y') : optional($certificate->created_at)->format('d.m.Y') }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Status</dt>
                        <dd class="text-[color:var(--nx-text)]">{{ $certificate->status?->label() }}</dd></div>
                </dl>

                @if($isEmployer)
                    <x-nx-callout variant="info" icon="heroicon-o-lock-closed" title="Schweigepflicht" class="mt-4">
                        Arbeitgeber-Ausfertigung: enthält keine medizinischen Befunde oder Ergebnisse.
                    </x-nx-callout>
                @endif
            </x-nx-card>
        </x-nx-section>

        {{-- Eingefrorener Inhalt: Leistungen — NUR nicht-Arbeitgeber (Schweigepflicht) --}}
        @unless($isEmployer)
        <x-nx-section icon="heroicon-o-clipboard-document-check" title="Leistungen">
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Leistung</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Ergebnis</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Nächste Fälligkeit</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @forelse(($content['services'] ?? []) as $row)
                            <x-nx-table-row>
                                <x-nx-table-cell>{{ $row['title'] ?? '—' }}</x-nx-table-cell>
                                <x-nx-table-cell>{{ $row['result'] ?? '—' }}</x-nx-table-cell>
                                <x-nx-table-cell>{{ $row['next_due'] ?? '—' }}</x-nx-table-cell>
                            </x-nx-table-row>
                        @empty
                            <x-nx-table-row>
                                <x-nx-table-cell>Keine Leistungen erfasst.</x-nx-table-cell>
                            </x-nx-table-row>
                        @endforelse
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        </x-nx-section>
        @endunless

        {{-- Textbausteine --}}
        @if(!empty($content['text_blocks']))
            <x-nx-section icon="heroicon-o-document-text" title="Textbausteine">
                <x-nx-card>
                    <div class="space-y-4">
                        @foreach($content['text_blocks'] as $block)
                            <div>
                                <h4 class="text-sm font-semibold text-[color:var(--nx-text)]">{{ $block['title'] ?? '' }}</h4>
                                <p class="text-sm text-[color:var(--nx-muted)] whitespace-pre-line">{{ $block['content'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-nx-card>
            </x-nx-section>
        @endif

        {{-- Zustellungen --}}
        <x-nx-section icon="heroicon-o-paper-airplane" title="Zustellungen" :hint="$certificate->deliveries->count()">
            @if($certificate->deliveries->isEmpty())
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-paper-airplane">Noch keine Zustellung erfasst.</x-nx-empty>
                </x-nx-card>
            @else
                <x-nx-card flush>
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Kanal</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Empfänger</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Versendet</x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach($certificate->deliveries as $delivery)
                                <x-nx-table-row wire:key="del-{{ $delivery->id }}">
                                    <x-nx-table-cell>{{ $delivery->channel?->label() }}</x-nx-table-cell>
                                    <x-nx-table-cell>{{ $delivery->recipient ?? '—' }}</x-nx-table-cell>
                                    <x-nx-table-cell>{{ optional($delivery->sent_at)->format('d.m.Y') ?? '—' }}</x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                </x-nx-card>
            @endif
        </x-nx-section>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Bezug</h3>
                    @if($certificate->appointment_id)
                        <a href="{{ route('encounter.appointments.show', $certificate->appointment_id) }}" wire:navigate
                           class="text-sm text-[color:var(--nx-accent)] hover:underline">Zum Termin</a>
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

    {{-- Zustellung erfassen --}}
    <x-nx-modal wire:model="showDeliveryModal" size="md">
        <x-slot name="header">Zustellung erfassen</x-slot>
        <div class="space-y-4">
            <x-nx-input-select name="deliveryForm.channel" label="Kanal" wire:model="deliveryForm.channel" :options="$channelOptions" />
            <x-nx-input-text name="deliveryForm.recipient" label="Empfänger" wire:model="deliveryForm.recipient" />
            <x-nx-input-date name="deliveryForm.sent_at" label="Versendet am" wire:model="deliveryForm.sent_at" />
        </div>
        <x-slot name="footer">
            <div class="flex justify-end gap-3">
                <x-nx-button variant="ghost" wire:click="$set('showDeliveryModal', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="addDelivery">Erfassen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
