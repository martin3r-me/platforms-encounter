<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_field_values — an einer erbrachten Leistung erfasste Feldwerte.
 *
 * `value` wird auf Model-Ebene verschlüsselt (Schweigepflicht). `key` ist ein Snapshot,
 * damit der Wert auch nach Löschen der Definition zuordenbar bleibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('service_id')->constrained('encounter_services')->cascadeOnDelete();
            $table->foreignId('field_definition_id')->nullable()
                ->constrained('encounter_field_definitions')->nullOnDelete();

            $table->string('key');
            $table->text('value')->nullable(); // verschlüsselt

            $table->timestamps();

            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_field_values');
    }
};
