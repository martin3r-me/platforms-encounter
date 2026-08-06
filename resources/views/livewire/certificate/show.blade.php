{{--
    Encounter · Bescheinigung-Detail (eingefroren) — nx-Design-System.
--}}
@php($content = $certificate->content ?? [])

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

        {{-- Kopf --}}
        <x-nx-section icon="heroicon-o-document-check" title="Bescheinigung">
            <x-nx-card>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-[color:var(--nx-muted)]">Zielgruppe</dt><dd class="text-[color:var(--nx-text)]">{{ $certificate->audience?->label() }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Status</dt><dd class="text-[color:var(--nx-text)]">{{ $certificate->status?->label() }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Patient</dt><dd class="text-[color:var(--nx-text)]">{{ $content['patient'] ?? $certificate->patient?->getDisplayName() ?? '—' }}</dd></div>
                    <div><dt class="text-[color:var(--nx-muted)]">Ausgestellt</dt><dd class="text-[color:var(--nx-text)]">{{ $content['issued_on'] ?? optional($certificate->created_at)->format('Y-m-d') }}</dd></div>
                </dl>
            </x-nx-card>
        </x-nx-section>

        {{-- Eingefrorener Inhalt: Leistungen --}}
        <x-nx-section icon="heroicon-o-clipboard-document-check" title="Leistungen">
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Leistung</x-nx-table-header-cell>
                        @if($certificate->audience !== \Platform\Encounter\Enums\Audience::Employer)
                            <x-nx-table-header-cell>Ergebnis</x-nx-table-header-cell>
                        @endif
                        <x-nx-table-header-cell>Nächste Fälligkeit</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @forelse(($content['services'] ?? []) as $row)
                            <x-nx-table-row>
                                <x-nx-table-cell>{{ $row['title'] ?? '—' }}</x-nx-table-cell>
                                @if($certificate->audience !== \Platform\Encounter\Enums\Audience::Employer)
                                    <x-nx-table-cell>{{ $row['result'] ?? '—' }}</x-nx-table-cell>
                                @endif
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
