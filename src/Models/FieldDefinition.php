<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;
use Platform\Encounter\Enums\Audience;
use Platform\Encounter\Enums\FieldType;

/**
 * FieldDefinition — team-anpassbare Feld-Definition (Variante A).
 *
 * Optional per morphMap an einen Katalog-Eintrag; audience-getaggt.
 *
 * @ai.description Team-anpassbare Feld-Definition für erfasste Werte.
 */
class FieldDefinition extends Model
{
    protected $table = 'encounter_field_definitions';

    protected $fillable = [
        'uuid',
        'team_id',
        'catalog_type',
        'catalog_id',
        'key',
        'label',
        'type',
        'audience',
        'position',
        'active',
        'options',
    ];

    protected $casts = [
        'type'     => FieldType::class,
        'audience' => Audience::class,
        'position' => 'integer',
        'active'   => 'boolean',
        'options'  => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = (string) UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }

            if (empty($model->team_id) && auth()->check()) {
                $model->team_id = auth()->user()->currentTeam?->id;
            }
        });
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where($this->getTable() . '.team_id', $teamId);
    }

    public function scopeForAudience(Builder $query, Audience $audience): Builder
    {
        return $query->where('audience', $audience->value);
    }

    public function catalog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'catalog_type', 'catalog_id');
    }
}
