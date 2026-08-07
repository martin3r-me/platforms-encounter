<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;
use Platform\Encounter\Enums\AppointmentStatus;

/**
 * Appointment — der Termin/Kontakt. Anker der klinischen Kette.
 *
 * Hängt (lose) an einem Patienten (patient_records, anderes Modul). Klinischer
 * Freitext ist verschlüsselt (Schweigepflicht).
 *
 * @ai.description Termin/Kontakt; Anker für erbrachte Leistungen, Anamnese/Befund, Recall.
 */
class Appointment extends Model
{
    use SoftDeletes;

    protected $table = 'encounter_appointments';

    protected $fillable = [
        'uuid',
        'team_id',
        'patient_id',
        'user_id',
        'scheduled_at',
        'status',
        'location_type',
        'doctor_entity_id',
        'performed_by',
        'doctor_stamp',
        'notes',
        'anamnesis',
        'findings',
        'remarks',
        'confidential',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'status'        => AppointmentStatus::class,
        'location_type' => \Platform\Encounter\Enums\LocationType::class,
        // Schweigepflicht: klinischer Freitext at-rest verschlüsselt.
        'anamnesis'    => 'encrypted',
        'findings'     => 'encrypted',
        'remarks'      => 'encrypted',
        'confidential' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = (string) UuidV7::generate();
                } while (self::withTrashed()->where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }

            if (empty($model->team_id) && auth()->check()) {
                $model->team_id = auth()->user()->currentTeam?->id;
            }

            if (empty($model->status)) {
                $model->status = AppointmentStatus::Planned->value;
            }
        });
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where($this->getTable() . '.team_id', $teamId);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'appointment_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'appointment_id');
    }

    /**
     * Lose Referenz auf den Patienten (patient-Modul). encounter darf auf patient hängen.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(\Platform\Patient\Models\Patient::class, 'patient_id');
    }
}
