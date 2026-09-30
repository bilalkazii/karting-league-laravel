<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('value', 255);
            $table->timestamps();

            $table->primary(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
