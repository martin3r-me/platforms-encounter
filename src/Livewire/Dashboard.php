<?php

namespace Platform\Encounter\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Platform\Encounter\Models\Appointment as AppointmentModel;

/**
 * Encounter-Dashboard = Tages-Agenda. Termine des gewählten Tages nach Uhrzeit;
 * Vor/Zurück-Blättern. (Wochen-/Zeitraster-Grid folgt später.)
 */
class Dashboard extends Component
{
    #[Url(as: 'd')]
    public string $date = '';

    public function mount(): void
    {
        if ($this->date === '' || !$this->isValidDate($this->date)) {
            $this->date = now()->toDateString();
        }
    }

    protected function isValidDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }

    public function goPrev(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function goNext(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function goToday(): void
    {
        $this->date = now()->toDateString();
    }

    public function render()
    {
        $team = Auth::user()?->currentTeam;

        $appointments = collect();
        if ($team) {
            $appointments = AppointmentModel::query()
                ->forTeam($team->id)
                ->with('patient')
                ->whereDate('scheduled_at', $this->date)
                ->orderBy('scheduled_at')
                ->get();
        }

        return view('encounter::livewire.dashboard', [
            'appointments' => $appointments,
            'day'          => Carbon::parse($this->date),
            'isToday'      => $this->date === now()->toDateString(),
        ])->layout('platform::layouts.app');
    }
}
