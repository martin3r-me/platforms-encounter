<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;
use Platform\Encounter\Enums\QuestionType;

/**
 * AnamnesisQuestion — eine Frage des Anamnese-Fragenkatalogs. Anlass-abhängig über
 * catalog()/morphMap (z.B. arbmedvv_occasion) und untersucher-abhängig (examiner_scope).
 *
 * @ai.description Anlassabhängige Anamnese-Frage (Fragenkatalog).
 */
class AnamnesisQuestion extends Model
{
    protected $table = 'encounter_anamnesis_questions';

    protected $fillable = [
        'uuid',
        'team_id',
        'catalog_type',
        'catalog_id',
        'text',
        'type',
        'options',
        'section',
        'examiner_scope',
        'position',
        'active',
        'created_by_user_id',
    ];

    protected $casts = [
        'type'    => QuestionType::class,
        'options' => 'array',
        'active'  => 'boolean',
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

    /** Katalog-Anlass (z.B. arbmedvv_occasion) per morphMap. */
    public function catalog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'catalog_type', 'catalog_id');
    }
}
