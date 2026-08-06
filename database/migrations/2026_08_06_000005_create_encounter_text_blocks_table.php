<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_text_blocks — wiederverwendbare Textbausteine (audience-getaggt).
 *
 * Optional per morphMap an einen Katalog-Eintrag gebunden (catalog_type/catalog_id) —
 * die team-anpassbare Dokument-Schicht (Variante A) lebt hier im Kern, nicht im Katalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_text_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();

            $table->string('catalog_type')->nullable();
            $table->unsignedBigInteger('catalog_id')->nullable();

            $table->string('title');
            $table->text('content')->nullable();
            $table->string('audience', 32); // Audience-Enum
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['catalog_type', 'catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_text_blocks');
    }
};
