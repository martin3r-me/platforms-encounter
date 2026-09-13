<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * PracticeService — ein Eintrag im team-lokalen Praxis-Leistungs-Katalog (frei pflegbar,
 * ohne DGUV-Verfahren/Anlass). Am Termin wählbar; per morphMap-Alias 'practice_service'
 * von der erbrachten Leistung (Service) referenziert.
 *
 * @ai.description Frei pflegbare Praxis-Leistung (kein DGUV-Grundsatz).
 */
class PracticeService extends Model
{
    use SoftDeletes;

    protected $table = 'encounter_practice_services';

    protected $fillable = [
        'uuid', 'team_id', 'title', 'description', 'active', 'position', 'created_by_user_id',
    ];

    protected $casts = [
        'active'   => 'boolean',
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) UuidV7::generate();
            }
            if (empty($model->team_id) && auth()->check()) {
                $model->team_id = auth()->user()->currentTeam?->id;
            }
            if (empty($model->created_by_user_id) && auth()->check()) {
                $model->created_by_user_id = auth()->id();
            }
        });
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where($this->getTable() . '.team_id', $teamId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
