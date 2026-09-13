<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Praxis-Leistungs-Katalog — team-lokale, frei pflegbare Leistungen OHNE DGUV-Verfahren/Anlass
 * (z.B. Reiseberatung, Ergonomie, Impfberatung …). Am Termin wählbar; erzeugt eine erbrachte
 * Leistung (encounter_services, catalog_type='practice_service'). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('encounter_practice_services')) {
            return;
        }

        Schema::create('encounter_practice_services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_practice_services');
    }
};
