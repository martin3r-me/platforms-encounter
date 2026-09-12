<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anamnese-Frage · Cluster Dauerzustand vs. Momentaufnahme.
 *
 * persistence:
 *   - 'snapshot'   = Momentaufnahme (gehört zum Vorgang, z.B. Blutdruck heute)
 *   - 'persistent' = Dauerzustand   (gehört zum Patienten, wird fortgeschrieben, z.B. Diabetes)
 *
 * Vorerst steuert das Feld die Gruppierung am Termin; die patientenweite Fortschreibung
 * (Zeitstrahl) baut später darauf auf.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('encounter_anamnesis_questions', 'persistence')) {
            return; // idempotent
        }

        Schema::table('encounter_anamnesis_questions', function (Blueprint $table) {
            $table->string('persistence', 16)->default('snapshot')->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('encounter_anamnesis_questions', function (Blueprint $table) {
            $table->dropColumn('persistence');
        });
    }
};
