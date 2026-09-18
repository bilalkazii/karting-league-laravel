<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_scoring', function (Blueprint $table) {
            $table->foreignId('season_id')->primary()->constrained('seasons')->cascadeOnDelete();
            $table->string('mode')->default('automatic');
            $table->integer('pole_position_points')->default(0);
            $table->integer('fastest_lap_points')->default(0);
            $table->integer('participation_points')->default(0);
            $table->integer('dnf_points')->default(0);
            $table->integer('dns_points')->default(0);
            $table->boolean('penalty_adjustment_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_scoring');
    }
};