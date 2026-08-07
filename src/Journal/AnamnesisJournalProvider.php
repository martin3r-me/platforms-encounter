<?php

namespace Platform\Encounter\Journal;

use Illuminate\Support\Str;
use Platform\Encounter\Contracts\JournalEntryProvider;
use Platform\Encounter\Models\Anamnesis;
use Platform\Encounter\Models\AnamnesisQuestion;

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

        // Anlass-Titel (arbmedvv) guarded auflösen.
        $occasionTitle = [];
        if (class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
            $occasionTitle = \Platform\Arbmedvv\Models\Occasion::query()
                ->where('team_id', $teamId)->pluck('title', 'id')->all();
        }

        $entries = [];
        foreach ($anamneses as $a) {
            $answers = $a->answers ?? [];

            $lines = [];
            foreach ($answers as $qid => $val) {
                $label = $questionText[(int) $qid] ?? ('Frage #' . $qid);
                $lines[] = Str::limit($label, 80) . ': ' . (is_scalar($val) ? (string) $val : json_encode($val));
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

            $entries[] = [
                'date'     => $a->appointment?->scheduled_at ?? $a->created_at,
                'anchor'   => 'anamnesis-' . $a->id,
                'type'     => 'anamnesis',
                'icon'     => 'heroicon-o-clipboard-document-list',
                'title'    => 'Anamnese',
                'subtitle' => $occTitle ? ('Anlass: ' . $occTitle) : 'ohne Anlass',
                'badge'    => $occTitle ? ['label' => $occTitle, 'variant' => 'default'] : null,
                'lines'    => $lines,
                'url'      => $a->appointment_id ? route('encounter.appointments.show', $a->appointment_id) : null,
            ];
        }

        return $entries;
    }
}
