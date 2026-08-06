<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_services — die an einem Termin erbrachte Leistung.
 *
 * Zeigt OPTIONAL per morphMap auf einen Katalog-Eintrag (catalog_type = Alias wie
 * 'arbmedvv_occasion', catalog_id) — der Kern bleibt katalog-agnostisch. `title` ist
 * ein Snapshot, damit die Leistung auch ohne geladenen Katalog selbstbeschreibend ist.
 * `next_due` trägt die Wiedervorlage-/Frist-Berechnung (Recall).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('appointment_id')->constrained('encounter_appointments')->cascadeOnDelete();

            // Polymorpher Katalog-Bezug (Variante A: Katalog = Referenz)
            $table->string('catalog_type')->nullable();
            $table->unsignedBigInteger('catalog_id')->nullable();
            $table->string('title'); // Snapshot des Katalog-Eintrags oder freie Leistung

            $table->string('examination_type', 16)->nullable(); // ExaminationType (eu/nu)
            $table->string('participation', 24)->nullable();     // Participation

            $table->boolean('interval_active')->default(false);
            $table->unsignedInteger('interval_months')->nullable();
            $table->date('next_due')->nullable()->index(); // Recall

            $table->text('assessment')->nullable(); // Beurteilung
            $table->string('result')->nullable();   // Ergebnis

            $table->timestamps();

            $table->index(['catalog_type', 'catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_services');
    }
};
