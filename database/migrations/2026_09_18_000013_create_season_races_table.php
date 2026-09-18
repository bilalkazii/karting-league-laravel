<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_races', function (Blueprint $table) {
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('race_id')->constrained('races')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->primary(['season_id', 'race_id']);
            $table->unique(['season_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_races');
    }
};