<?php

namespace Platform\Encounter\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

/**
 * Encounter-Haupt-Sidebar — Kalender/Modul-Links + der Betrieb-Baum als Kontext-Linse
 * (gleiche Graph-Navigation wie customer/patient). Klick auf einen Knoten filtert die
 * Terminliste auf die Personen dieses Teilbaums. Der Betrieb-Baum kommt aus customer
 * (guarded — ohne customer bleibt encounter rein zeitlich navigierbar).
 */
class Sidebar extends Component
{
    public function render()
    {
        $team = Auth::user()?->currentTeam?->id;

        $nodes = [];
        if ($team && class_exists(\Platform\Customer\Support\Companies::class)) {
            $tree = \Platform\Customer\Support\Companies::tree((int) $team);

            // Termin-Anzahl je Patient (eine Aggregat-Query) — Basis für die Knoten-Badges.
            $apptByPatient = \Platform\Encounter\Models\Appointment::query()
                ->forTeam((int) $team)
                ->selectRaw('patient_id, COUNT(*) as c')
                ->groupBy('patient_id')
                ->pluck('c', 'patient_id');

            // Counts nur bei überschaubarem Baum berechnen (sonst zu viele Subtree-Queries).
            $withCounts = count($tree) <= 40;

            foreach ($tree as $n) {
                $count = null;
                if ($withCounts) {
                    $count = $this->nodeAppointmentCount((int) $n['id'], (int) $team, $apptByPatient);
                }
                $nodes[] = [
                    'id'    => $n['id'],
                    'label' => $n['name'],
                    'depth' => $n['depth'],
                    'url'   => route('encounter.appointments.index', ['node' => $n['id']]),
                    'count' => $count,
                ];
            }
        }

        return view('encounter::livewire.sidebar', [
            'nodes'    => $nodes,
            'activeId' => request()->query('node'),
        ]);
    }

    /** Anzahl Termine im Teilbaum eines Betrieb-Knotens (guarded — 0 wenn nicht ermittelbar). */
    protected function nodeAppointmentCount(int $nodeId, int $teamId, $apptByPatient): int
    {
        try {
            $entityIds = \Platform\Customer\Support\Companies::subtreeIds($nodeId, $teamId);
            $patients  = resolve(\Platform\Customer\Services\CompanyPatientRegistry::class)
                ->patientsFor($entityIds, $teamId);

            $sum = 0;
            foreach ($patients as $p) {
                $sum += (int) ($apptByPatient[$p['patient_id']] ?? 0);
            }
            return $sum;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
