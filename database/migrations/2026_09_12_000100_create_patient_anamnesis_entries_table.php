<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patientenweite Dauerfakten (Stufe: fortgeschriebene Anamnese).
 *
 * Ein Eintrag = ein Dauerfakt eines Patienten mit Gültigkeit. Antworten auf 'persistent'-Fragen
 * werden am Termin hierher fortgeschrieben (bestätigen = last_confirmed_* ; ändern = alte Zeile
 * valid_until, neue Zeile). valid_until NULL = gilt aktuell. Grundlage des Zeitstrahls.
 * Klinischer Wert verschlüsselt (Schweigepflicht).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('encounter_patient_anamnesis_entries')) {
            return; // idempotent
        }

        Schema::create('encounter_patient_anamnesis_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('patient_id')->index();

            $table->unsignedBigInteger('question_id')->nullable()->index();
            $table->text('question_snapshot')->nullable();   // Fragetext zum Erfassungszeitpunkt
            $table->text('value')->nullable();               // verschlüsselt (Cast)

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();          // NULL = laufend
            $table->string('source')->nullable();             // Angabe der Person / Fremdbefund / eigene Erhebung

            $table->unsignedBigInteger('first_appointment_id')->nullable();
            $table->unsignedBigInteger('last_confirmed_appointment_id')->nullable();
            $table->timestamp('last_confirmed_at')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'patient_id', 'question_id'], 'epae_team_patient_question_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_patient_anamnesis_entries');
    }
};
