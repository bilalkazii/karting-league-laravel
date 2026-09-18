<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_last_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->foreignId('race_id')->nullable()->constrained('races')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'group_id']);
            $table->unique(['driver_id', 'race_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_last_reads');
    }
};
