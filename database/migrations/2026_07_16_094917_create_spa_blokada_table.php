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
        Schema::create('spa_blokada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zgrada_id')->constrained('zgrada')->cascadeOnDelete();
            $table->date('datum');
            $table->unsignedTinyInteger('slot_index')->nullable(); // null = ceo dan zatvoren
            $table->string('razlog')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['zgrada_id', 'datum']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spa_blokada');
    }
};
