<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Practice — Praxis-Profil/Briefkopf je Team (Settings). Ein Datensatz pro Team.
 *
 * @ai.description Praxis-Briefkopf (Name, Adresse, Arzt, Unterschrift) je Team.
 */
class Practice extends Model
{
    protected $table = 'encounter_practices';

    protected $fillable = [
        'team_id',
        'name',
        'street',
        'postal_code',
        'city',
        'phone',
        'physician',
        'physician_suffix',
        'signature',
    ];

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where($this->getTable() . '.team_id', $teamId);
    }

    /**
     * Praxis-Profil des Teams holen (oder ein leeres, ungespeichertes Modell).
     */
    public static function forTeamOrNew(int $teamId): self
    {
        return static::query()->where('team_id', $teamId)->first()
            ?? new static(['team_id' => $teamId]);
    }
}
