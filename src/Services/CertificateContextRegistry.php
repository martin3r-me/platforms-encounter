<?php

namespace Platform\Encounter\Services;

use Platform\Encounter\Contracts\CertificateContextProvider;

/**
 * CertificateContextRegistry — sammelt die Fachmodul-Kontextgeber (occupational, später
 * andere Fachrichtungen) und liefert den gemergten Bescheinigungs-Kontext eines Patienten.
 * Singleton; Fachmodule rufen ->register(...) in ihrem boot().
 */
class CertificateContextRegistry
{
    /** @var array<int,CertificateContextProvider> */
    protected array $providers = [];

    public function register(CertificateContextProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Gemergter Kontext: erster gefundener Arbeitgeber gewinnt; Vorsorge-Pflichten werden
     * über alle Provider zusammengeführt.
     */
    public function contextFor(int $patientId, int $teamId): array
    {
        $employer   = null;
        $provisions = [];

        foreach ($this->providers as $provider) {
            try {
                $ctx = $provider->contextFor($patientId, $teamId);
            } catch (\Throwable $e) {
                continue; // ein defekter Provider darf die Bescheinigung nicht brechen
            }

            if (!is_array($ctx)) {
                continue;
            }

            if ($employer === null && !empty($ctx['employer'])) {
                $employer = $ctx['employer'];
            }
            foreach ($ctx['provisions'] ?? [] as $p) {
                $provisions[] = $p;
            }
        }

        return ['employer' => $employer, 'provisions' => $provisions];
    }
}
