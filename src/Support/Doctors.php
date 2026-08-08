<?php

namespace Platform\Encounter\Support;

/**
 * Doctors — Behandler-Roster aus dem practice-Modul (guarded, ohne harte Abhängigkeit).
 * Behandler sind Person-Entities unter einem Praxis-Standort (PracticeDoctor).
 */
class Doctors
{
    /** @return array<int,string> person_entity_id => Label (Titel Name) */
    public static function options(int $teamId): array
    {
        if (!class_exists(\Platform\Practice\Models\PracticeDoctor::class)) {
            return [];
        }

        $docs = \Platform\Practice\Models\PracticeDoctor::query()->where('team_id', $teamId)->get();
        if ($docs->isEmpty()) {
            return [];
        }

        $names = \Platform\Organization\Models\OrganizationEntity::query()
            ->whereIn('id', $docs->pluck('person_entity_id'))->pluck('name', 'id');

        $out = [];
        foreach ($docs as $d) {
            $out[(int) $d->person_entity_id] = trim(
                ($d->title ? $d->title . ' ' : '') . ($names[$d->person_entity_id] ?? ('#' . $d->person_entity_id))
            );
        }

        return $out;
    }

    /** person_entity_id des mit dem User verknüpften Arztes („meine Termine"), sonst null. */
    public static function forUser(int $teamId, int $userId): ?int
    {
        // Primär: kanonische „wer bin ich" aus dem Org-Graphen (linked_user_id) — arzt-
        // unabhängig, funktioniert für jedes verknüpfte Personal.
        if (class_exists(\Platform\Organization\Support\CurrentPerson::class)) {
            try {
                $id = \Platform\Organization\Support\CurrentPerson::entityId($userId);
                if ($id) {
                    return $id;
                }
            } catch (\Throwable $e) {
                // organization nicht verfügbar — Fallback unten.
            }
        }

        // Fallback: „Das bin ich" aus practice_doctors (Legacy, wenn keine Org-Verknüpfung).
        if (!class_exists(\Platform\Practice\Models\PracticeDoctor::class)) {
            return null;
        }

        try {
            $id = \Platform\Practice\Models\PracticeDoctor::query()
                ->where('team_id', $teamId)->where('user_id', $userId)->value('person_entity_id');
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Deterministische Farbe je Behandler. */
    public static function color(int $entityId): string
    {
        $palette = ['#2563eb', '#16a34a', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#db2777', '#65a30d'];

        return $palette[$entityId % count($palette)];
    }
}
