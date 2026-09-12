<?php

namespace Platform\Encounter\Livewire\Anamnesis;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Platform\Patient\Models\Patient as PatientModel;
use Platform\Encounter\Models\PatientAnamnesisEntry;

/**
 * Anamnese-Zeitstrahl — die patientenweiten Dauerfakten als Bänder auf gemeinsamer Zeitachse.
 * „Metformin seit 2023, laufend" = langes Band; „Bandscheibenvorfall 03/2025" = kurzes. Read-only.
 */
class Timeline extends Component
{
    #[Locked]
    public int $patientId;

    public function mount(int $patient): void
    {
        $this->patientId = $this->resolvePatient($patient)->id;
    }

    protected function resolvePatient(int $id): PatientModel
    {
        $team = (int) Auth::user()->currentTeam->id;

        return PatientModel::query()->forTeam($team)->findOrFail($id);
    }

    public function render()
    {
        $team    = (int) Auth::user()->currentTeam->id;
        $patient = $this->resolvePatient($this->patientId);

        $entries = PatientAnamnesisEntry::query()->forTeam($team)->forPatient($this->patientId)
            ->orderBy('question_id')->orderBy('valid_from')->orderBy('id')
            ->get();

        $today = Carbon::now()->startOfDay();

        // Zeitachse spannen: früheste valid_from … heute (bzw. spätestes valid_until).
        $min = $today->copy();
        $max = $today->copy();
        foreach ($entries as $e) {
            $from = $e->valid_from ? Carbon::parse($e->valid_from) : $today;
            $to   = $e->valid_until ? Carbon::parse($e->valid_until) : $today;
            if ($from->lt($min)) { $min = $from->copy(); }
            if ($to->gt($max))   { $max = $to->copy(); }
        }
        $min = $min->copy()->subDays(30);
        $totalDays = max(1, $min->diffInDays($max));

        // Bänder je Dauerfakt (nach Frage gruppiert).
        $rows = [];
        foreach ($entries->groupBy('question_id') as $qid => $group) {
            $label = $group->first()->question_snapshot ?: 'Dauerfakt';
            $bands = [];
            foreach ($group as $e) {
                $from = $e->valid_from ? Carbon::parse($e->valid_from) : $today;
                $to   = $e->valid_until ? Carbon::parse($e->valid_until) : $today;
                $offset = ($min->diffInDays($from) / $totalDays) * 100;
                $width  = max(1.5, ($from->diffInDays($to) / $totalDays) * 100);
                $bands[] = [
                    'value'  => (string) $e->value,
                    'from'   => $e->valid_from ? Carbon::parse($e->valid_from)->format('m.Y') : '—',
                    'until'  => $e->valid_until ? Carbon::parse($e->valid_until)->format('m.Y') : null,
                    'open'   => $e->valid_until === null,
                    'offset' => round(min(100, max(0, $offset)), 2),
                    'width'  => round(min(100, $width), 2),
                ];
            }
            $rows[] = ['label' => $label, 'bands' => $bands];
        }

        // Jahres-Marken für die Achse.
        $years = [];
        for ($y = (int) $min->year; $y <= (int) $max->year; $y++) {
            $mark = Carbon::create($y, 1, 1)->startOfDay();
            if ($mark->lt($min) || $mark->gt($max)) { continue; }
            $years[] = ['label' => (string) $y, 'offset' => round(($min->diffInDays($mark) / $totalDays) * 100, 2)];
        }

        return view('encounter::livewire.anamnesis.timeline', [
            'patient' => $patient,
            'rows'    => $rows,
            'years'   => $years,
        ])->layout('platform::layouts.app');
    }
}
