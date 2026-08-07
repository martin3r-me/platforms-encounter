<?php

namespace Platform\Encounter\Services;

use Platform\Encounter\Contracts\LetterheadProvider;

/**
 * LetterheadRegistry — sammelt Briefkopf-Provider. Der Provider mit der höchsten Priorität,
 * der Daten liefert, gewinnt (practice-Modul > encounter-interner Fallback).
 */
class LetterheadRegistry
{
    /** @var array<int,LetterheadProvider> */
    protected array $providers = [];

    public function register(LetterheadProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>|null
     */
    public function letterheadFor(int $teamId, array $context = []): ?array
    {
        $providers = $this->providers;
        usort($providers, fn ($a, $b) => $b->letterheadPriority() <=> $a->letterheadPriority());

        foreach ($providers as $provider) {
            try {
                $data = $provider->letterheadFor($teamId, $context);
                if (!empty($data)) {
                    return $data;
                }
            } catch (\Throwable $e) {
                // Ein defekter Provider darf das Dokument nicht brechen.
            }
        }

        return null;
    }
}
