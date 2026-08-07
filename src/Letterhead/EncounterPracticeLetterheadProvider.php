<?php

namespace Platform\Encounter\Letterhead;

use Platform\Encounter\Contracts\LetterheadProvider;
use Platform\Encounter\Models\Practice;

/**
 * Fallback-Briefkopf aus dem encounter-internen Praxis-Profil (ein Datensatz je Team).
 * Priorität 0 — wird vom practice-Modul (per Standort + Arzt) überstimmt, sobald vorhanden.
 */
class EncounterPracticeLetterheadProvider implements LetterheadProvider
{
    public function letterheadPriority(): int
    {
        return 0;
    }

    public function letterheadFor(int $teamId, array $context = []): ?array
    {
        $p = Practice::query()->forTeam($teamId)->first();
        if (!$p) {
            return null;
        }

        $addressLines = array_values(array_filter([
            trim((string) ($p->street ?? '')),
            trim(($p->postal_code ?? '') . ' ' . ($p->city ?? '')),
        ], fn ($l) => $l !== ''));

        $doctor = array_filter([
            'name'          => $p->physician,
            'specialty'     => $p->physician_suffix,
            'signature_url' => $p->signature ?: null, // data-URI
        ]);

        return [
            'source'        => 'encounter',
            'name'          => $p->name,
            'address_lines' => $addressLines,
            'contact_lines' => array_values(array_filter([$p->phone ? 'Tel. ' . $p->phone : null])),
            'bsnr'          => null,
            'logo_url'      => null,
            'stamp_url'     => null,
            'doctor'        => $doctor ?: null,
        ];
    }
}
