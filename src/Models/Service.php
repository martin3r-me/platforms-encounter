<?php

namespace Platform\Encounter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Platform\Encounter\Enums\ExaminationType;
use Platform\Encounter\Enums\Participation;

/**
 * Service — eine an einem Termin erbrachte Leistung.
 *
 * Referenziert OPTIONAL per morphMap einen Katalog-Eintrag (arbmedvv_occasion, …).
 * `title` ist ein Snapshot; `next_due` trägt den Recall.
 *
 * @ai.description Erbrachte Leistung an einem Termin; zeigt polymorph auf einen Katalog-Eintrag.
 */
class Service extends Model
{
    protected $table = 'encounter_services';

    protected $fillable = [
        'team_id',
        'appointment_id',
        'catalog_type',
        'catalog_id',
        'title',
        'examination_type',
        'participation',
        'interval_active',
        'interval_months',
        'next_due',
        'assessment',
        'result',
    ];

    protected $casts = [
        'examination_type' => ExaminationType::class,
        'participation'    => Participation::class,
        'interval_active'  => 'boolean',
        'interval_months'  => 'integer',
        'next_due'         => 'date',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(FieldValue::class, 'service_id');
    }

    /**
     * Recall-Scope: fällige Leistungen bis asOf + lookaheadDays.
     */
    public function scopeDue(Builder $query, $asOf = null, int $lookaheadDays = 30): Builder
    {
        $threshold = ($asOf ? Carbon::parse($asOf) : Carbon::now())
            ->copy()->addDays($lookaheadDays)->endOfDay();

        return $query->whereNotNull('next_due')->where('next_due', '<=', $threshold);
    }

    /**
     * Recall-Status: overdue | due | soon | null.
     */
    public function recallStatus($asOf = null, int $soonDays = 30): ?string
    {
        if (!$this->next_due) {
            return null;
        }

        $asOf = $asOf ? Carbon::parse($asOf) : Carbon::now();

        if ($this->next_due->lt($asOf->copy()->startOfDay())) {
            return 'overdue';
        }

        if ($this->next_due->lte($asOf->copy()->addDays($soonDays)->endOfDay())) {
            return 'due';
        }

        return 'soon';
    }

    public function recallStatusLabel($asOf = null): ?string
    {
        return match ($this->recallStatus($asOf)) {
            'overdue' => 'Überfällig',
            'due'     => 'Fällig',
            'soon'    => 'Bald fällig',
            default   => null,
        };
    }

    /**
     * Polymorpher Katalog-Bezug — auflösbar, sobald das jeweilige Katalog-Modul
     * (arbmedvv, …) seinen morphMap-Alias registriert hat. Sonst bleibt es ein String.
     */
    public function catalog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'catalog_type', 'catalog_id');
    }

    /**
     * Nächste Fälligkeit = Termin-Datum + Intervall (Monate), wenn Intervall aktiv.
     */
    public function computeNextDue(): ?\Illuminate\Support\Carbon
    {
        if (!$this->interval_active || !$this->interval_months) {
            return null;
        }

        $base = $this->appointment?->scheduled_at;

        return $base ? $base->copy()->addMonths((int) $this->interval_months)->startOfDay() : null;
    }
}
