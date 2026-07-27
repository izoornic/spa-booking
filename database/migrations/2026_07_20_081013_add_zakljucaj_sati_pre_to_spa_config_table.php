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
        Schema::table('spa_config', function (Blueprint $table) {
            // Hours before slot start when the slot locks: from here on every reservation
            // in it is guaranteed (trajna) and can no longer be displaced.
            $table->unsignedSmallInteger('zakljucaj_sati_pre')->default(1)->after('podsetnik_sati_pre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spa_config', function (Blueprint $table) {
            $table->dropColumn('zakljucaj_sati_pre');
        });
    }
};
