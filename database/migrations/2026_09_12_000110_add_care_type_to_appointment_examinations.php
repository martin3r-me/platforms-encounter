<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Art der Vorsorge je Termin-Verfahren (Pflicht/Angebot/Wunsch/Nachgehend).
 * Nur bei Vorsorge relevant; steuert später das Standard-Intervall (Recall).
 * Werte wie arbmedvv.care_type: mandatory|offered|request|follow_up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('encounter_appointment_examinations', 'care_type')) {
            return;
        }

        Schema::table('encounter_appointment_examinations', function (Blueprint $table) {
            $table->string('care_type', 16)->nullable()->after('examination_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('encounter_appointment_examinations', 'care_type')) {
            return;
        }

        Schema::table('encounter_appointment_examinations', function (Blueprint $table) {
            $table->dropColumn('care_type');
        });
    }
};
