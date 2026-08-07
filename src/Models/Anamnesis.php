<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Anamnesis — die erfasste Anamnese eines Termins/Kontakts (Stufe B). Antworten auf
 * Fragen des Fragenkatalogs (Stufe A), anlassbezogen (catalog → arbmedvv_occasion),
 * plus Freitext. Klinischer Inhalt verschlüsselt (Schweigepflicht). Je Kontakt ein
 * Snapshot → Basis der Delta-Historie (Stufe C).
 *
 * @ai.description Erfasste Anamnese je Termin (Antworten + Freitext, anlassbezogen).
 */
class Anamnesis extends Model
{
    protected $table = 'encounter_anamneses';

    protected $fillable = [
        'uuid',
        'team_id',
        'patient_id',
        'appointment_id',
        'catalog_type',
        'catalog_id',
        'answers',
        'free_text',
        'version',
        'created_by_user_id',
    ];

    protected $casts = [
        // Schweigepflicht: klinischer Inhalt at-rest verschlüsselt.
        'answers'   => 'encrypted:array',
        'free_text' => 'encrypted',
        'version'   => 'integer',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /** Anlass-Katalog (z.B. arbmedvv_occasion) per morphMap. */
    public function catalog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'catalog_type', 'catalog_id');
    }
}
