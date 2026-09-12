<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termin ↔ Verfahren — je Termin 1..n Untersuchungen (examinations).
 * Treiber der Anamnese: die Fragen sind die Vereinigung der Fragen aller gewählten Verfahren
 * plus das Basismodul (verfahrensunabhängige Fragen).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('encounter_appointment_examinations')) {
            return; // idempotent: Tabelle wurde in einem früheren Lauf bereits angelegt
        }

        Schema::create('encounter_appointment_examinations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->index();
            $table->unsignedBigInteger('examination_id')->index();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['appointment_id', 'examination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_appointment_examinations');
    }
};
