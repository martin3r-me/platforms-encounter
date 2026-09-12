<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anamnese-Fragen · Ausbaustufe 3 — Bindung von Anlass → Verfahren umhängen.
 *
 * Für jede Frage mit catalog_type='arbmedvv_occasion', deren Anlass inzwischen einem Verfahren
 * zugeordnet ist (arbmedvv_occasions.examination_id gesetzt), wird die Bindung auf
 * ('examination', examination_id) geändert — die Frage hängt danach am VERFAHREN.
 *
 * Fragen an Anlässen OHNE Verfahren bleiben unverändert. Fragen ohne Anlass (Basismodul,
 * catalog_type NULL) sind nicht betroffen.
 *
 * Läuft NACH der arbmedvv-Verknüpfung (Migrations-Reihenfolge 000030 < 000040).
 * Einbahn: die Antwort-Historie ist über encounter_anamneses.questions_snapshot geschützt,
 * daher kein automatisches down().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE encounter_anamnesis_questions q
            JOIN arbmedvv_occasions o
              ON o.team_id = q.team_id AND o.id = q.catalog_id
            SET q.catalog_type = 'examination',
                q.catalog_id   = o.examination_id,
                q.updated_at   = NOW()
            WHERE q.catalog_type = 'arbmedvv_occasion'
              AND o.examination_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Nicht sauber umkehrbar (mehrere Anlässe → ein Verfahren; die Herkunfts-Anlass-ID ist
        // nach dem Umhängen nicht mehr eindeutig rekonstruierbar). Bewusst kein Down.
    }
};
