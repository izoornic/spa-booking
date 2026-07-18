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
        Schema::create('vlasnik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stan_id')->constrained('stan')->cascadeOnDelete();
            $table->string('ime');
            $table->string('prezime');
            $table->string('email')->nullable();
            $table->string('telefon')->nullable();
            $table->string('token', 64)->unique();
            $table->boolean('aktivan')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vlasnik');
    }
};
