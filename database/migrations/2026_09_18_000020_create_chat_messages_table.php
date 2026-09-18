<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->foreignId('race_id')->nullable()->constrained('races')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('group_id');
            $table->index('race_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
