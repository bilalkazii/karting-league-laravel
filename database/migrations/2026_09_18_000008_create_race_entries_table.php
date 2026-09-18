<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('race_entries', function (Blueprint $table) {
            $table->foreignId('race_id')->constrained('races')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->unsignedInteger('kart_number');
            $table->string('status')->default('invited');
            $table->boolean('confirmed')->default(false);
            $table->boolean('ready')->default(false);
            $table->unsignedInteger('grid_position')->nullable();
            $table->integer('grid_penalty_seconds')->default(0);
            $table->unsignedBigInteger('qualifying_time_ms')->nullable();
            $table->string('qualifying_status')->default('not_started');
            $table->unsignedInteger('finish_position')->nullable();
            $table->integer('penalty_total_seconds')->default(0);
            $table->text('notes')->default('');
            $table->primary(['race_id', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_entries');
    }
};