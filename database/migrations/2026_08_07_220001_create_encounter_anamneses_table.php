<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erfasste Anamnese (Stufe B) — EINE Anamnese je Termin/Kontakt, ANLASS-bezogen
 * (catalog_type/catalog_id → arbmedvv_occasion). answers/free_text sind klinischer
 * Freitext → at-rest verschlüsselt (Schweigepflicht). Je Kontakt ein Snapshot →
 * Grundlage der versionierten Delta-Historie (Stufe C).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_anamneses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();

            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();

            // Anlass-Bindung (morphMap, z.B. arbmedvv_occasion) — null = ohne Anlass
            $table->string('catalog_type')->nullable();
            $table->unsignedBigInteger('catalog_id')->nullable();

            // Klinischer Inhalt — verschlüsselt (Schweigepflicht)
            $table->text('answers')->nullable();      // encrypted:array {question_id: value}
            $table->text('free_text')->nullable();    // encrypted freitext

            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();
            $table->index(['catalog_type', 'catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_anamneses');
    }
};
