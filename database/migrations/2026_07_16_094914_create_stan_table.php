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
        Schema::create('stan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zgrada_id')->constrained('zgrada')->cascadeOnDelete();
            $table->string('broj');
            $table->string('sprat')->nullable();
            $table->boolean('ima_dug')->default(false);
            $table->timestamps();

            $table->unique(['zgrada_id', 'broj']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stan');
    }
};
