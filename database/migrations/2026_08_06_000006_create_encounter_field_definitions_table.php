<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_field_definitions — team-anpassbare Feld-Definitionen (Variante A).
 *
 * Optional per morphMap an einen Katalog-Eintrag gebunden (catalog null = teamweiter
 * Standard). `audience` steuert, in welches Dokument der erfasste Wert fließt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();

            $table->string('catalog_type')->nullable();
            $table->unsignedBigInteger('catalog_id')->nullable();

            $table->string('key');
            $table->string('label');
            $table->string('type', 16)->default('text'); // FieldType-Enum
            $table->string('audience', 32);               // Audience-Enum
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->json('options')->nullable();          // für type=select

            $table->timestamps();

            $table->index(['catalog_type', 'catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_field_definitions');
    }
};
