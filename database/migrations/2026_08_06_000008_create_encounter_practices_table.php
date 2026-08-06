<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_practices — Praxis-Profil/Briefkopf je Team (Settings).
 * Ein Datensatz pro Team (team_id unique). Fließt in Bescheinigungen/PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_practices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->unique();

            $table->string('name')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('physician')->nullable();        // verantwortliche:r Arzt/Ärztin
            $table->string('physician_suffix')->nullable(); // Zusatz (Facharzt-Bezeichnung o.ä.)
            $table->text('signature')->nullable();          // Unterschrift (data-URI)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_practices');
    }
};
