<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termin-Setup: Ort der Erbringung (Im Haus/Betrieb/Hausbesuch/Telemedizin) +
 * Behandler (doctor_entity_id → Person-Entity aus dem Praxis-Ärzte-Roster).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounter_appointments', function (Blueprint $table) {
            $table->string('location_type', 16)->default('practice')->after('status');
            $table->unsignedBigInteger('doctor_entity_id')->nullable()->after('location_type'); // Behandler (Person-Entity)
        });
    }

    public function down(): void
    {
        Schema::table('encounter_appointments', function (Blueprint $table) {
            $table->dropColumn(['location_type', 'doctor_entity_id']);
        });
    }
};
