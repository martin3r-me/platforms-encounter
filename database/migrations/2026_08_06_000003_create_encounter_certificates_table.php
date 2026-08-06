<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_certificates — Bescheinigung mit EINGEFRORENEM, audience-gefiltertem
 * Inhalts-Snapshot (content json). Bleibt als Rechtsdokument erhalten, auch wenn der
 * Quell-Termin gelöscht wird (KEIN Cascade auf appointment_id, lose gekoppelt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();

            $table->unsignedBigInteger('appointment_id')->nullable()->index(); // lose, KEIN Cascade
            $table->unsignedBigInteger('patient_id')->nullable()->index();     // lose Referenz

            $table->string('audience', 32); // Audience-Enum
            $table->string('title');
            $table->json('content')->nullable(); // eingefrorener Snapshot
            $table->string('status', 32)->default('issued');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_certificates');
    }
};
