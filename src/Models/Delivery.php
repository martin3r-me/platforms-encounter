<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Encounter\Enums\DeliveryChannel;

/**
 * Delivery — Zustellnachweis einer Bescheinigung (Kanal, Empfänger, Fristen).
 *
 * @ai.description Zustellnachweis einer Bescheinigung.
 */
class Delivery extends Model
{
    protected $table = 'encounter_certificate_deliveries';

    protected $fillable = [
        'team_id',
        'certificate_id',
        'channel',
        'recipient',
        'sent_at',
        'delivered_at',
        'confirmed_at',
        'comms_ref',
    ];

    protected $casts = [
        'channel'      => DeliveryChannel::class,
        'sent_at'      => 'date',
        'delivered_at' => 'date',
        'confirmed_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->team_id) && auth()->check()) {
                $model->team_id = auth()->user()->currentTeam?->id;
            }
        });
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where($this->getTable() . '.team_id', $teamId);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'certificate_id');
    }
}
