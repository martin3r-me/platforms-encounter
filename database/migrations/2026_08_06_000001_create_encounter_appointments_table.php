<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_appointments — der Termin/Kontakt (fachneutral).
 *
 * patient_id ist eine LOSE Referenz auf patient_records (anderes Modul) — kein DB-FK,
 * um die Module unabhängig migrierbar zu halten. Klinischer Freitext
 * (anamnesis/findings/remarks/confidential) wird auf Model-Ebene verschlüsselt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

            $table->unsignedBigInteger('patient_id')->index(); // lose Referenz → patient_records
            $table->unsignedBigInteger('user_id')->nullable()->index(); // durchführende:r User

            $table->dateTime('scheduled_at')->index();
            $table->string('status', 32)->default('planned')->index();

            $table->string('performed_by')->nullable(); // Arztname (freitext)
            $table->string('doctor_stamp')->nullable();
            $table->text('notes')->nullable();

            // Klinischer Freitext — verschlüsselt (Schweigepflicht)
            $table->text('anamnesis')->nullable();
            $table->text('findings')->nullable();     // Befund
            $table->text('remarks')->nullable();      // Bemerkungen
            $table->text('confidential')->nullable(); // Schweigepflicht-Notizen

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_appointments');
    }
};
