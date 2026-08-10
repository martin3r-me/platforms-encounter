<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * questions_snapshot — der Fragetext ZUM ANTWORTZEITPUNKT, je beantworteter Frage
 * ({question_id: text}). Macht die Anamnese-Historie robust gegen spätere Änderungen
 * am Fragenkatalog (Umformulierung/Löschung): die alte Antwort behält ihren Kontext.
 * Klinischer Inhalt → verschlüsselt (Schweigepflicht), wie answers/free_text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounter_anamneses', function (Blueprint $table) {
            $table->text('questions_snapshot')->nullable()->after('answers'); // encrypted:array {qid: text}
        });
    }

    public function down(): void
    {
        Schema::table('encounter_anamneses', function (Blueprint $table) {
            $table->dropColumn('questions_snapshot');
        });
    }
};
