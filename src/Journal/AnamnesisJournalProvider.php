<?php

namespace Platform\Encounter\Journal;

use Illuminate\Support\Str;
use Platform\Encounter\Contracts\JournalEntryProvider;
use Platform\Encounter\Models\Anamnesis;
use Platform\Encounter\Models\AnamnesisQuestion;
use Platform\Encounter\Services\AnamnesisHistory;

/**
 * Liefert die erfassten Anamnesen (Stufe B) eines Patienten als Verlauf-Einträge —
 * anlassbezogen, mit Kurzfassung der beantworteten Fragen. Eigener Eintrag je Kontakt
 * (Grundlage der Delta-Historie, Stufe C).
 */
class AnamnesisJournalProvider implements JournalEntryProvider
{
    public function entriesFor(int $patientId, int $teamId): array
    {
        $anamneses = Anamnesis::query()
            ->forTeam($teamId)
            ->where('patient_id', $patientId)
            ->with('appointment')
            ->orderByDesc('id')
            ->get();

        if ($anamneses->isEmpty()) {
            return [];
        }

        // Fragen-Texte einmalig cachen (für die Zeilen-Kurzfassung).
        $questionText = AnamnesisQuestion::query()->forTeam($teamId)
            ->pluck('text', 'id')->all();

        // Delta-Historie (Stufe C): pro Anamnese die geänderten/neuen Antworten.
        $changes = AnamnesisHistory::forPatient($patientId, $teamId)['changes'] ?? [];

        // Anlass-Titel (arbmedvv) guarded auflösen.
        $occasionTitle = [];
        if (class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
            $occasionTitle = \Platform\Arbmedvv\Models\Occasion::query()
                ->where('team_id', $teamId)->pluck('title', 'id')->all();
        }

        $entries = [];
        foreach ($anamneses as $a) {
            $answers = $a->answers ?? [];

            $delta = $changes[$a->id] ?? [];
            $changedCount = 0;

            $lines = [];
            foreach ($answers as $qid => $val) {
                $qid   = (int) $qid;
                $label = $questionText[$qid] ?? ('Frage #' . $qid);
                $value = is_scalar($val) ? (string) $val : json_encode($val);

                // Delta-Marker: neu / geändert ggü. letztem Kontakt.
                $marker = '';
                if (isset($delta[$qid])) {
                    $changedCount++;
                    if (!empty($delta[$qid]['new'])) {
                        $marker = ' (neu)';
                    } else {
                        $from = $delta[$qid]['from'];
                        $marker = ' (vorher: ' . (is_scalar($from) ? (string) $from : json_encode($from)) . ')';
                    }
                }

                $lines[] = Str::limit($label, 80) . ': ' . $value . $marker;
                if (count($lines) >= 6) {
                    $lines[] = '…';
                    break;
                }
            }
            if (!empty($a->free_text)) {
                $lines[] = 'Ergänzung: ' . Str::limit((string) $a->free_text, 200);
            }
            if (empty($lines)) {
                $lines[] = 'Anamnese erfasst (keine Angaben).';
            }

            $occTitle = ($a->catalog_type === 'arbmedvv_occasion' && $a->catalog_id)
                ? ($occasionTitle[(int) $a->catalog_id] ?? null)
                : null;

            // Änderungs-Badge dominiert (Stufe C): „N geändert" statt Anlass, wenn Deltas da sind.
            $badge = $occTitle ? ['label' => $occTitle, 'variant' => 'default'] : null;
            if ($changedCount > 0) {
                $badge = ['label' => $changedCount . ' geändert', 'variant' => 'warning'];
            }

            $entries[] = [
                'date'     => $a->appointment?->scheduled_at ?? $a->created_at,
                'anchor'   => 'anamnesis-' . $a->id,
                'type'     => 'anamnesis',
                'icon'     => 'heroicon-o-clipboard-document-list',
                'title'    => 'Anamnese',
                'subtitle' => $occTitle ? ('Anlass: ' . $occTitle) : 'ohne Anlass',
                'badge'    => $badge,
                'lines'    => $lines,
                'url'      => route('encounter.anamnesis.history', $patientId),
            ];
        }

        return $entries;
    }
}
