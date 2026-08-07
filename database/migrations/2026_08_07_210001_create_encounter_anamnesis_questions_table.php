<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anamnese-Fragenkatalog (Stufe A) — team-anpassbare Fragen, ANLASS-abhängig
 * (catalog_type/catalog_id → arbmedvv_occasion) und UNTERSUCHER-abhängig (examiner_scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_anamnesis_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();

            // Anlass-Bindung (morphMap, z.B. arbmedvv_occasion) — null = allgemein
            $table->string('catalog_type')->nullable();
            $table->unsignedBigInteger('catalog_id')->nullable();

            $table->text('text');                                  // die Frage
            $table->string('type', 16)->default('yes_no');         // QuestionType
            $table->json('options')->nullable();                   // für choice/scale
            $table->string('section')->nullable();                 // Gruppierung
            $table->string('examiner_scope', 24)->nullable();      // null = alle, sonst z.B. arzt/assistenz
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();
            $table->index(['catalog_type', 'catalog_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_anamnesis_questions');
    }
};
