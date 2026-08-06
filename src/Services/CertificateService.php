<?php

namespace Platform\Encounter\Services;

use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Models\Certificate;
use Platform\Encounter\Models\TextBlock;
use Platform\Encounter\Enums\Audience;
use Platform\Encounter\Enums\CertificateStatus;

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
     * Baut den audience-gefilterten Inhalt eines Termins.
     */
    public function buildContent(Appointment $appointment, Audience $audience): array
    {
        $showResult = $audience !== Audience::Employer;

        $services = $appointment->services->map(function ($s) use ($showResult) {
            $row = [
                'title'    => $s->title,
                'next_due' => optional($s->next_due)->toDateString(),
            ];
            if ($showResult) {
                $row['result'] = $s->result;
            }
            return $row;
        })->values()->all();

        $blocks = TextBlock::query()
            ->forTeam((int) $appointment->team_id)
            ->where('active', true)
            ->where('audience', $audience->value)
            ->orderBy('position')
            ->get()
            ->map(fn (TextBlock $b) => ['title' => $b->title, 'content' => $b->content])
            ->all();

        return [
            'audience'     => $audience->value,
            'patient'      => $appointment->patient?->getDisplayName(),
            'scheduled_at' => optional($appointment->scheduled_at)->toDateString(),
            'services'     => $services,
            'text_blocks'  => $blocks,
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
