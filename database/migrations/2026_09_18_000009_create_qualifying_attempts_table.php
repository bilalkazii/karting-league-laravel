<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualifying_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_id')->constrained('races')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->unsignedBigInteger('time_ms')->nullable();
            $table->string('status')->default('not_started');
            $table->timestamp('recorded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualifying_attempts');
    }
};