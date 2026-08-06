<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * encounter_certificate_deliveries — Zustellnachweis einer Bescheinigung
 * (Kanal, Empfänger, versendet/zugestellt/bestätigt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounter_certificate_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('certificate_id')->constrained('encounter_certificates')->cascadeOnDelete();

            $table->string('channel', 16); // DeliveryChannel-Enum
            $table->string('recipient')->nullable();
            $table->date('sent_at')->nullable();
            $table->date('delivered_at')->nullable();
            $table->date('confirmed_at')->nullable();
            $table->string('comms_ref')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_certificate_deliveries');
    }
};
