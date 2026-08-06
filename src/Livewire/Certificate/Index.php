<?php

namespace Platform\Encounter\Livewire\Certificate;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Certificate as CertificateModel;

class Index extends Component
{
    public function render()
    {
        $team = Auth::user()->currentTeam;

        $certificates = CertificateModel::query()
            ->forTeam($team->id)
            ->with('patient')
            ->orderByDesc('created_at')
            ->get();

        return view('encounter::livewire.certificate.index', [
            'certificates' => $certificates,
        ])->layout('platform::layouts.app');
    }
}
