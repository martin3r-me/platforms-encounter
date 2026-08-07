<?php

namespace Platform\Encounter\Services;

use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Models\Certificate;
use Platform\Encounter\Models\TextBlock;
use Platform\Encounter\Models\Anamnesis;
use Platform\Encounter\Enums\Audience;
use Platform\Encounter\Enums\CertificateStatus;
use Platform\Encounter\Services\CertificateContextRegistry;
use Platform\Encounter\Services\LetterheadRegistry;

/**
 * CertificateService — stellt Bescheinigungen aus (DocumentBuilder + Freeze).
 *
 * Audience-Filter erzwingt die Schweigepflicht-Trennung: der Arbeitgeber (Employer)
 * erhält KEINE medizinischen Ergebnisse (result wird ausgelassen). Der Inhalt wird zum
 * Ausstellungszeitpunkt eingefroren (content-json) und ändert sich nicht rückwirkend.
 */
class CertificateService
{
    /**
     * Baut den audience-gefilterten Inhalt eines Termins nach AMR 6.3.
     *
     * Schweigepflicht: der Arbeitgeber (Employer) erhält Person + Anlass + Fristen, aber
     * KEINE medizinischen Ergebnisse (Leistungs-result/Befund). Alle Referenzen (Person,
     * Firma, Anlass, Arzt) werden hier eingefroren.
     */
    public function buildContent(Appointment $appointment, Audience $audience): array
    {
        $appointment->loadMissing(['services', 'patient']);
        $team       = (int) $appointment->team_id;
        $isEmployer = $audience === Audience::Employer;

        // --- Person (AMR 6.3: Name + Geburtsdatum) ---
        $patient = $appointment->patient;
        $person  = [
            'name'       => $patient?->getDisplayName(),
            'birth_date' => optional($patient?->birth_date)->toDateString(),
        ];

        // --- Vorsorgeanlass aus der Anamnese dieses Termins ---
        $occasionId    = null;
        $occasionTitle = null;
        $anamnesis = Anamnesis::query()->forTeam($team)
            ->where('appointment_id', $appointment->id)->latest('id')->first();
        if ($anamnesis && $anamnesis->catalog_type === 'arbmedvv_occasion' && $anamnesis->catalog_id) {
            $occasionId = (int) $anamnesis->catalog_id;
            if (class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
                $occasionTitle = \Platform\Arbmedvv\Models\Occasion::query()
                    ->where('team_id', $team)->whereKey($occasionId)->value('title');
            }
        }

        // --- Fachlicher Kontext (occupational): Arbeitgeber + Vorsorgeart/Frist, graph-nativ ---
        $ctx = ['employer' => null, 'provisions' => []];
        try {
            $ctx = resolve(CertificateContextRegistry::class)->contextFor((int) ($patient?->id ?? 0), $team);
        } catch (\Throwable $e) {
            // Fachmodul nicht geladen — Bescheinigung bleibt gültig, ohne Firma/Art.
        }
        $employer = $ctx['employer'] ?? null;

        // Art + nächste Vorsorge: passende Provision zum Anlass, sonst erste.
        $careType = null;
        $nextDue  = null;
        foreach (($ctx['provisions'] ?? []) as $p) {
            if ($occasionId && (int) ($p['occasion_id'] ?? 0) === $occasionId) {
                $careType = $p['care_type'] ?? null;
                $nextDue  = $p['next_due'] ?? null;
                break;
            }
        }
        // Fallback: früheste Recall-Frist aus den erbrachten Leistungen.
        if (!$nextDue) {
            $nextDue = optional($appointment->services->pluck('next_due')->filter()->min())->toDateString();
        }

        // --- Leistungen — NUR wenn nicht Arbeitgeber (Schweigepflicht) ---
        $services = [];
        if (!$isEmployer) {
            $services = $appointment->services->map(fn ($s) => [
                'title'    => $s->title,
                'result'   => $s->result,
                'next_due' => optional($s->next_due)->toDateString(),
            ])->values()->all();
        }

        // --- Textbausteine (audience-gefiltert) ---
        $blocks = TextBlock::query()
            ->forTeam($team)
            ->where('active', true)
            ->where('audience', $audience->value)
            ->orderBy('position')
            ->get()
            ->map(fn (TextBlock $b) => ['title' => $b->title, 'content' => $b->content])
            ->all();

        // --- Briefkopf (Arzt/Praxis) einfrieren ---
        $letterhead = null;
        try {
            $letterhead = resolve(LetterheadRegistry::class)
                ->letterheadFor($team, ['appointment_id' => $appointment->id]);
        } catch (\Throwable $e) {
            // Briefkopf optional.
        }

        return [
            'audience'     => $audience->value,
            'person'       => $person,
            'employer'     => $employer,
            'occasion'     => ['id' => $occasionId, 'title' => $occasionTitle, 'care_type' => $careType],
            'examined_on'  => optional($appointment->scheduled_at)->toDateString(),
            'next_due'     => $nextDue,
            'services'     => $services,
            'text_blocks'  => $blocks,
            'letterhead'   => $letterhead,
            'issued_on'    => now()->toDateString(),
        ];
    }

    /**
     * Stellt eine Bescheinigung aus (eingefroren).
     */
    public function issue(Appointment $appointment, Audience $audience, ?string $title = null): Certificate
    {
        $appointment->loadMissing(['services', 'patient']);

        $title = $title ?: match ($audience) {
            Audience::Employer => 'Vorsorgebescheinigung (Arbeitgeber)',
            Audience::Patient  => 'Vorsorgebescheinigung',
            default            => 'Bescheinigung (' . $audience->label() . ')',
        };

        return Certificate::create([
            'team_id'        => $appointment->team_id,
            'appointment_id' => $appointment->id,
            'patient_id'     => $appointment->patient_id,
            'audience'       => $audience->value,
            'title'          => $title,
            'content'        => $this->buildContent($appointment, $audience),
            'status'         => CertificateStatus::Issued->value,
        ]);
    }
}
