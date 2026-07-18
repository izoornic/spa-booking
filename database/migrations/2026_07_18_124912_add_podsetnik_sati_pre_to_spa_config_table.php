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
            // Hours before slot start to send the reminder email; 0 disables reminders.
            $table->unsignedSmallInteger('podsetnik_sati_pre')->default(3)->after('min_sati_pre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spa_config', function (Blueprint $table) {
            $table->dropColumn('podsetnik_sati_pre');
        });
    }
};
