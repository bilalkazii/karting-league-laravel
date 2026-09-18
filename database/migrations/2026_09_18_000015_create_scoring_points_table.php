<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->integer('points');
            $table->unique(['season_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_points');
    }
};