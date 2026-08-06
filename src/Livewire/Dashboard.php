<?php

namespace Platform\Encounter\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Platform\Encounter\Models\Appointment as AppointmentModel;
use Platform\Encounter\Models\Service as ServiceModel;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $team = $user?->currentTeam;

        $stats = ['today' => 0, 'due' => 0];

        if ($team) {
            $stats['today'] = AppointmentModel::query()
                ->forTeam($team->id)
                ->whereDate('scheduled_at', Carbon::today())
                ->count();

            $stats['due'] = ServiceModel::query()
                ->forTeam($team->id)
                ->due()
                ->count();
        }

        return view('encounter::livewire.dashboard', [
            'stats'       => $stats,
            'currentDate' => now()->format('d.m.Y'),
        ])->layout('platform::layouts.app');
    }
}
