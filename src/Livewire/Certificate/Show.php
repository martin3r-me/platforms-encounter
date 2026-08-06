<?php

namespace Platform\Encounter\Livewire\Certificate;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Encounter\Models\Certificate as CertificateModel;
use Platform\Encounter\Models\Delivery as DeliveryModel;
use Platform\Encounter\Enums\CertificateStatus;
use Platform\Encounter\Enums\DeliveryChannel;

class Show extends Component
{
    #[Locked]
    public int $certificateId;

    public bool $showDeliveryModal = false;
    public array $deliveryForm = [
        'channel' => 'print',
        'recipient' => '',
        'sent_at' => null,
    ];

    public function mount(int $certificate): void
    {
        $this->certificateId = $this->resolve($certificate)->id;
    }

    protected function resolve(int $id): CertificateModel
    {
        $team = Auth::user()->currentTeam;

        return CertificateModel::query()->forTeam($team->id)->findOrFail($id);
    }

    public function addDelivery(): void
    {
        $this->validate([
            'deliveryForm.channel'   => ['required', 'string'],
            'deliveryForm.recipient' => ['nullable', 'string', 'max:255'],
            'deliveryForm.sent_at'   => ['nullable', 'date'],
        ]);

        if (!DeliveryChannel::tryFrom($this->deliveryForm['channel'])) {
            $this->addError('deliveryForm.channel', 'Ungültiger Kanal.');
            return;
        }

        $certificate = $this->resolve($this->certificateId);

        DeliveryModel::create([
            'team_id'        => $certificate->team_id,
            'certificate_id' => $certificate->id,
            'channel'        => $this->deliveryForm['channel'],
            'recipient'      => $this->deliveryForm['recipient'] ?: null,
            'sent_at'        => $this->deliveryForm['sent_at'] ?: null,
        ]);

        $this->reset('deliveryForm');
        $this->showDeliveryModal = false;
        $this->dispatch('toast', message: 'Zustellung erfasst.', type: 'success');
    }

    public function revoke(): void
    {
        $certificate = $this->resolve($this->certificateId);
        $certificate->update(['status' => CertificateStatus::Revoked->value]);
        $this->dispatch('toast', message: 'Bescheinigung widerrufen.', type: 'success');
    }

    public function render()
    {
        $certificate = $this->resolve($this->certificateId)->load(['deliveries', 'patient']);

        return view('encounter::livewire.certificate.show', [
            'certificate'    => $certificate,
            'channelOptions' => DeliveryChannel::cases(),
        ])->layout('platform::layouts.app');
    }
}
