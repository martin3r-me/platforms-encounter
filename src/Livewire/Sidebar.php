<?php

namespace Platform\Encounter\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Appointment as AppointmentModel;

class Sidebar extends Component
{
    public function render()
    {
        $user = Auth::user();
        $appointments = collect();

        if ($user && $user->currentTeam) {
            $appointments = AppointmentModel::query()
                ->forTeam($user->currentTeam->id)
                ->with('patient')
                ->orderByDesc('scheduled_at')
                ->limit(15)
                ->get();
        }

        return view('encounter::livewire.sidebar', [
            'appointments' => $appointments,
        ]);
    }
}
