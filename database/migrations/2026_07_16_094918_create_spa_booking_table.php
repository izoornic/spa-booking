<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spa_booking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zgrada_id')->constrained('zgrada')->cascadeOnDelete();
            $table->foreignId('stan_id')->constrained('stan')->cascadeOnDelete();
            $table->foreignId('vlasnik_id')->nullable()->constrained('vlasnik')->nullOnDelete();
            $table->date('datum');
            $table->unsignedTinyInteger('slot_index'); // 1..broj_slotova
            $table->unsignedSmallInteger('broj_osoba');
            $table->string('stanje')->default('booked');
            $table->boolean('je_trajna')->default(false);
            $table->string('qr_token', 64)->nullable()->unique();
            $table->unsignedSmallInteger('evidentirano_osoba')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['zgrada_id', 'datum', 'slot_index']);
            $table->index(['stan_id', 'datum']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spa_booking');
    }
};
