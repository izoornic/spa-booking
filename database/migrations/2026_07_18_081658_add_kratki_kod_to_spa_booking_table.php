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
            // Short, human-typeable code the attendant can enter when a QR scan isn't possible.
            $table->string('kratki_kod', 12)->nullable()->unique()->after('qr_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spa_booking', function (Blueprint $table) {
            $table->dropUnique(['kratki_kod']);
            $table->dropColumn('kratki_kod');
        });
    }
};
