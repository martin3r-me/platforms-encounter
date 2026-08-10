{{--
    Bescheinigung — dedizierte PDF-/Druckvorlage (DomPDF-tauglich: Tabellen + Inline-CSS,
    kein flex/grid). Rendert den EINGEFRORENEN content → automatisch audience-korrekt
    (beim Arbeitgeber ist services=[], also erscheinen keine Befunde).
--}}
@php
    $content   = $certificate->content ?? [];
    $lh        = $content['letterhead'] ?? null;
    $doc       = $lh['doctor'] ?? null;
    $person    = $content['person'] ?? [];
    $employer  = $content['employer'] ?? null;
    $occasion  = $content['occasion'] ?? [];
    $services  = $content['services'] ?? [];
    $blocks    = $content['text_blocks'] ?? [];
    $isEmployer = ($content['audience'] ?? null) === 'employer';
    $isRevoked = $certificate->status === \Platform\Encounter\Enums\CertificateStatus::Revoked;
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') : '—';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
    * { font-family: 'DejaVu Sans', sans-serif; }
    @page { margin: 22mm 18mm; }
    body { font-size: 11pt; color: #1a1a1a; margin: 0; }
    .lh-name { font-size: 13pt; font-weight: bold; }
    .muted { color: #555; }
    .faint { color: #888; font-size: 9pt; }
    .hr { border-top: 1px solid #bbb; margin: 10px 0 16px; }
    h1 { font-size: 15pt; margin: 4px 0 2px; }
    .sub { color: #555; font-size: 10pt; margin-bottom: 14px; }
    table.fields { width: 100%; border-collapse: collapse; }
    table.fields td { vertical-align: top; padding: 5px 10px 5px 0; width: 50%; }
    .label { color: #555; font-size: 9pt; }
    .value { font-size: 11pt; }
    .block { margin-top: 14px; }
    .block-title { font-weight: bold; font-size: 10.5pt; margin-bottom: 2px; }
    .note { background: #f2f6ff; border: 1px solid #cdd9f0; padding: 8px 10px; font-size: 9.5pt; color: #333; margin-top: 16px; }
    .revoked { color: #b00020; font-weight: bold; border: 2px solid #b00020; padding: 6px 10px; margin-bottom: 14px; }
    img.logo { max-height: 56px; max-width: 180px; }
    img.sig { max-height: 46px; max-width: 180px; }
    img.stamp { max-height: 64px; max-width: 120px; }
    table.sign { width: 100%; margin-top: 44px; }
    table.sign td { width: 50%; vertical-align: bottom; }
    .sign-line { border-top: 1px solid #333; padding-top: 4px; font-size: 9pt; color: #555; }
</style>
</head>
<body>

    {{-- Briefkopf --}}
    @if ($lh)
        <table style="width:100%"><tr>
            <td style="vertical-align:top">
                <div class="lh-name">{{ $lh['name'] ?? 'Praxis' }}</div>
                @foreach (($lh['address_lines'] ?? []) as $line)<div class="muted">{{ $line }}</div>@endforeach
                @if (!empty($lh['contact_lines']))<div class="faint">{{ implode(' · ', $lh['contact_lines']) }}</div>@endif
                @if (!empty($lh['bsnr']))<div class="faint">BSNR {{ $lh['bsnr'] }}</div>@endif
            </td>
            @if (!empty($lh['logo_url']))
                <td style="text-align:right;vertical-align:top"><img class="logo" src="{{ $lh['logo_url'] }}" alt=""></td>
            @endif
        </tr></table>
        <div class="hr"></div>
    @endif

    @if ($isRevoked)
        <div class="revoked">WIDERRUFEN — diese Bescheinigung ist ungültig.</div>
    @endif

    <h1>{{ $certificate->title }}</h1>
    <div class="sub">Pflichtangaben nach AMR 6.3</div>

    <table class="fields">
        <tr>
            <td><div class="label">Beschäftigte:r</div><div class="value">{{ $person['name'] ?? '—' }}</div></td>
            <td><div class="label">Geburtsdatum</div><div class="value">{{ $fmt($person['birth_date'] ?? null) }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Arbeitgeber</div><div class="value">{{ $employer['name'] ?? '—' }}</div></td>
            <td><div class="label">Anlass der Vorsorge</div><div class="value">{{ $occasion['title'] ?? '—' }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Art der Vorsorge</div><div class="value">{{ $occasion['care_type'] ?? '—' }}</div></td>
            <td><div class="label">Datum der Vorsorge</div><div class="value">{{ $fmt($content['examined_on'] ?? null) }}</div></td>
        </tr>
        <tr>
            <td><div class="label">Nächste Vorsorge (spätestens)</div><div class="value">{{ $fmt($content['next_due'] ?? null) }}</div></td>
            <td><div class="label">Ausgestellt am</div><div class="value">{{ $fmt($content['issued_on'] ?? optional($certificate->created_at)->toDateString()) }}</div></td>
        </tr>
    </table>

    {{-- Leistungen: erscheinen nur, wenn im content vorhanden (beim Arbeitgeber leer → Schweigepflicht). --}}
    @if (!empty($services))
        <div class="block">
            <div class="block-title">Erbrachte Leistungen</div>
            <table class="fields">
                @foreach ($services as $s)
                    <tr><td colspan="2">
                        <div class="value">{{ $s['title'] ?? '—' }}@if (!empty($s['result'])) — {{ $s['result'] }}@endif</div>
                        @if (!empty($s['next_due']))<div class="faint">nächste Fälligkeit: {{ $fmt($s['next_due']) }}</div>@endif
                    </td></tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- Textbausteine (audience-gefiltert beim Einfrieren) --}}
    @foreach ($blocks as $b)
        <div class="block">
            @if (!empty($b['title']))<div class="block-title">{{ $b['title'] }}</div>@endif
            @if (!empty($b['content']))<div class="value">{!! nl2br(e($b['content'])) !!}</div>@endif
        </div>
    @endforeach

    @if ($isEmployer)
        <div class="note">Diese Bescheinigung enthält gemäß ärztlicher Schweigepflicht keine medizinischen Befunde — nur Anlass, Durchführung und Fristen der arbeitsmedizinischen Vorsorge.</div>
    @endif

    {{-- Unterschrift / Arzt + Stempel --}}
    <table class="sign"><tr>
        <td>
            @if (!empty($doc['signature_url']))<img class="sig" src="{{ $doc['signature_url'] }}" alt=""><br>@endif
            <div class="sign-line">
                {{ trim(($doc['title'] ?? '') . ' ' . ($doc['name'] ?? '')) ?: 'Ausstellende:r Ärztin/Arzt' }}@if (!empty($doc['specialty'])) · {{ $doc['specialty'] }}@endif@if (!empty($doc['lanr'])) · LANR {{ $doc['lanr'] }}@endif
            </div>
        </td>
        <td style="text-align:right;vertical-align:bottom">
            @if (!empty($lh['stamp_url']))<img class="stamp" src="{{ $lh['stamp_url'] }}" alt="">@endif
        </td>
    </tr></table>

</body>
</html>
