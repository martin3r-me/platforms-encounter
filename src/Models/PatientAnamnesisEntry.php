<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * PatientAnamnesisEntry — ein patientenweiter Dauerfakt mit Gültigkeit.
 *
 * Wird aus 'persistent'-Anamnese-Fragen am Termin fortgeschrieben. valid_until NULL = laufend.
 * Basis des Zeitstrahls. Klinischer Wert at-rest verschlüsselt (Schweigepflicht).
 *
 * @ai.description Patientenweiter Dauerfakt (fortgeschriebene Anamnese) mit Gültigkeitszeitraum.
 */
class PatientAnamnesisEntry extends Model
{
    use SoftDeletes;

    protected $table = 'encounter_patient_anamnesis_entries';

    protected $fillable = [
        'uuid', 'team_id', 'patient_id',
        'question_id', 'question_snapshot', 'value',
        'valid_from', 'valid_until', 'source',
        'first_appointment_id', 'last_confirmed_appointment_id', 'last_confirmed_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'value'             => 'encrypted',
        'valid_from'        => 'date',
        'valid_until'       => 'date',
        'last_confirmed_at' => 'datetime',
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

    public function scopeForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where($this->getTable() . '.patient_id', $patientId);
    }

    /** Aktuell gültige Dauerfakten (nicht beendet). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('valid_until');
    }
}
