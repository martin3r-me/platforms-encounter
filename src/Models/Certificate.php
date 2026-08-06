<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;
use Platform\Encounter\Enums\Audience;
use Platform\Encounter\Enums\CertificateStatus;

/**
 * Certificate — Bescheinigung mit eingefrorenem, audience-gefiltertem Inhalts-Snapshot.
 *
 * Einmal ausgestellt ist `content` unveränderlich; spätere Änderungen an Leistungen
 * ändern eine ausgestellte Bescheinigung nicht. Bleibt als Rechtsdokument erhalten.
 *
 * @ai.description Ausgestellte Bescheinigung (eingefroren, audience-gefiltert).
 */
class Certificate extends Model
{
    protected $table = 'encounter_certificates';

    protected $fillable = [
        'uuid',
        'team_id',
        'appointment_id',
        'patient_id',
        'audience',
        'title',
        'content',
        'status',
    ];

    protected $casts = [
        'content'  => 'array',
        'audience' => Audience::class,
        'status'   => CertificateStatus::class,
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

            if (empty($model->status)) {
                $model->status = CertificateStatus::Issued->value;
            }
        });
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where($this->getTable() . '.team_id', $teamId);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'certificate_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(\Platform\Patient\Models\Patient::class, 'patient_id');
    }
}
