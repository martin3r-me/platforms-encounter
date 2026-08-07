<?php

namespace Platform\Encounter\Services;

use Platform\Encounter\Models\Anamnesis;
use Platform\Encounter\Models\AnamnesisQuestion;

/**
 * AnamnesisHistory (Stufe C) — baut den versionierten Anamnese-Verlauf eines Patienten
 * über alle Kontakte hinweg und erkennt Deltas (was hat sich ggü. dem zuletzt bekannten
 * Wert geändert?). Carry-forward-Semantik: eine Frage, die in einem späteren Kontakt nicht
 * erneut erhoben wird, behält ihren letzten Wert; ein abweichender Wert ist eine Änderung.
 */
class AnamnesisHistory
{
    /**
     * @return array{
     *   contacts: array<int,array{id:int,date:\DateTimeInterface|null,occasion:?string}>,
     *   questions: array<int,string>,
     *   values: array<int,array<int,mixed>>,          // [contactId][questionId] => value
     *   changes: array<int,array<int,array{from:mixed,to:mixed,new:bool}>>  // nur geänderte/neue
     * }
     */
    public static function forPatient(int $patientId, int $teamId): array
    {
        // Chronologisch AUFSTEIGEND, damit Deltas gegen den jeweils vorherigen Wert laufen.
        $anamneses = Anamnesis::query()
            ->forTeam($teamId)
            ->where('patient_id', $patientId)
            ->with('appointment')
            ->get()
            ->sortBy(fn ($a) => $a->appointment?->scheduled_at ?? $a->created_at)
            ->values();

        if ($anamneses->isEmpty()) {
            return ['contacts' => [], 'questions' => [], 'values' => [], 'changes' => []];
        }

        $questionText = AnamnesisQuestion::query()->forTeam($teamId)->pluck('text', 'id')->all();

        $occasionTitle = [];
        if (class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
            $occasionTitle = \Platform\Arbmedvv\Models\Occasion::query()
                ->where('team_id', $teamId)->pluck('title', 'id')->all();
        }

        $contacts = [];
        $values   = [];
        $changes  = [];
        $usedQuestionIds = [];
        $lastSeen = [];   // question_id => zuletzt bekannter Wert

        foreach ($anamneses as $a) {
            $answers = $a->answers ?? [];
            $values[$a->id]  = [];
            $changes[$a->id] = [];

            foreach ($answers as $qid => $val) {
                $qid = (int) $qid;
                $usedQuestionIds[$qid] = true;
                $values[$a->id][$qid] = $val;

                $prev = $lastSeen[$qid] ?? null;
                if ($prev === null) {
                    $changes[$a->id][$qid] = ['from' => null, 'to' => $val, 'new' => true];
                } elseif ($prev !== $val) {
                    $changes[$a->id][$qid] = ['from' => $prev, 'to' => $val, 'new' => false];
                }
                $lastSeen[$qid] = $val;
            }

            $occ = ($a->catalog_type === 'arbmedvv_occasion' && $a->catalog_id)
                ? ($occasionTitle[(int) $a->catalog_id] ?? null)
                : null;

            $contacts[] = [
                'id'       => $a->id,
                'date'     => $a->appointment?->scheduled_at ?? $a->created_at,
                'occasion' => $occ,
            ];
        }

        // Nur tatsächlich verwendete Fragen, in stabiler Reihenfolge.
        $questions = [];
        foreach (array_keys($usedQuestionIds) as $qid) {
            $questions[$qid] = $questionText[$qid] ?? ('Frage #' . $qid);
        }

        // Kontakte neueste zuerst für die Anzeige.
        $contacts = array_reverse($contacts);

        return [
            'contacts'  => $contacts,
            'questions' => $questions,
            'values'    => $values,
            'changes'   => $changes,
        ];
    }
}
