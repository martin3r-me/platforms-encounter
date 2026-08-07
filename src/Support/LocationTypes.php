<?php

namespace Platform\Encounter\Support;

use Platform\Encounter\Enums\LocationType;

/**
 * LocationTypes — im Termin-Editor angebotene Orte. Honoriert die je Praxis-Standort
 * freigeschalteten Termin-Arten (Union über alle Standorte); Fallback = alle 4.
 * Liest practice guarded (keine harte Abhängigkeit).
 */
class LocationTypes
{
    /** @return array<string,string> value => label */
    public static function allowed(int $teamId): array
    {
        $all = LocationType::options();

        if (!class_exists(\Platform\Practice\Models\PracticeProfile::class)) {
            return $all;
        }

        try {
            $sets = \Platform\Practice\Models\PracticeProfile::query()
                ->where('team_id', $teamId)
                ->whereNotNull('appointment_location_types')
                ->pluck('appointment_location_types');

            $union = [];
            foreach ($sets as $s) {
                if (is_array($s)) {
                    foreach ($s as $v) {
                        $union[$v] = true;
                    }
                }
            }

            return empty($union) ? $all : array_intersect_key($all, $union);
        } catch (\Throwable $e) {
            return $all;
        }
    }
}
