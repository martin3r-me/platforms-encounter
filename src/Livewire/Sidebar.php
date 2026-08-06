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
            foreach (\Platform\Customer\Support\Companies::tree((int) $team) as $n) {
                $nodes[] = [
                    'id'    => $n['id'],
                    'label' => $n['name'],
                    'depth' => $n['depth'],
                    'url'   => route('encounter.appointments.index', ['node' => $n['id']]),
                ];
            }
        }

        return view('encounter::livewire.sidebar', [
            'nodes'    => $nodes,
            'activeId' => request()->query('node'),
        ]);
    }
}
