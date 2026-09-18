<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->string('availability')->default('available');
            $table->timestamp('joined_at')->nullable();
            $table->primary(['group_id', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};