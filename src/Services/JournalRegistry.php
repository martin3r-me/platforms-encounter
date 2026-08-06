<?php

namespace Platform\Encounter\Services;

use Platform\Encounter\Contracts\JournalEntryProvider;

/**
 * JournalRegistry — sammelt die Verlauf-Provider der Fachmodule und liefert den
 * gemergten, nach Datum absteigend sortierten Verlauf (Akte) eines Patienten.
 * Singleton; Fachmodule rufen ->register(...) in ihrem boot().
 */
class JournalRegistry
{
    /** @var array<int,JournalEntryProvider> */
    protected array $providers = [];

    public function register(JournalEntryProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return array<int,array<string,mixed>> neueste zuerst
     */
    public function entriesFor(int $patientId, int $teamId): array
    {
        $entries = [];

        foreach ($this->providers as $provider) {
            try {
                foreach ($provider->entriesFor($patientId, $teamId) as $entry) {
                    if (!empty($entry['date'])) {
                        $entries[] = $entry;
                    }
                }
            } catch (\Throwable $e) {
                // Ein defekter Provider darf den Verlauf nicht brechen.
            }
        }

        usort($entries, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return $entries;
    }
}
