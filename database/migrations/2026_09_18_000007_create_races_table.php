<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('venue_name');
            $table->date('date');
            $table->time('start_time');
            $table->string('format')->default('sprint');
            $table->string('status')->default('draft');
            $table->foreignId('organizer_id')->constrained('drivers');
            $table->unsignedInteger('qualifying_lap_count')->default(1);
            $table->text('rules');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('races');
    }
};