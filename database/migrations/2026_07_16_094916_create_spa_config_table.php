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
        Schema::create('spa_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zgrada_id')->unique()->constrained('zgrada')->cascadeOnDelete();
            $table->unsignedSmallInteger('kapacitet')->default(25);
            $table->unsignedSmallInteger('max_rez_7d')->default(4);
            $table->unsignedSmallInteger('max_osoba')->default(5);
            $table->unsignedSmallInteger('horizont_dana')->default(7);
            $table->time('radno_od')->default('12:00');
            $table->time('radno_do')->default('21:00');
            $table->unsignedTinyInteger('broj_slotova')->default(3);
            $table->unsignedTinyInteger('min_sati_pre')->default(2);
            $table->boolean('blokiraj_dug')->default(false);
            $table->boolean('aktivan')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spa_config');
    }
};
