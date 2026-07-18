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
        Schema::table('spa_booking', function (Blueprint $table) {
            // When the reminder email was sent (null = not yet), prevents duplicate reminders.
            $table->timestamp('podsetnik_poslat_at')->nullable()->after('evidentirano_osoba');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spa_booking', function (Blueprint $table) {
            $table->dropColumn('podsetnik_poslat_at');
        });
    }
};
