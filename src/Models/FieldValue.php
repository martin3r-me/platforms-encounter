<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FieldValue — an einer erbrachten Leistung erfasster Feldwert (verschlüsselt).
 *
 * @ai.description Erfasster, verschlüsselter Feldwert einer erbrachten Leistung.
 */
class FieldValue extends Model
{
    protected $table = 'encounter_field_values';

    protected $fillable = [
        'team_id',
        'service_id',
        'field_definition_id',
        'key',
        'value',
    ];

    protected $casts = [
        // Schweigepflicht: erfasster Wert at-rest verschlüsselt.
        'value' => 'encrypted',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(FieldDefinition::class, 'field_definition_id');
    }
}
