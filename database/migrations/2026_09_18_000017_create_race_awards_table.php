<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('race_awards', function (Blueprint $table) {
            $table->foreignId('race_id')->primary()->constrained('races')->cascadeOnDelete();
            $table->foreignId('driver_of_race_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('most_improved_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('cleanest_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_awards');
    }
};