<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('logo_initials')->default('');
            $table->string('logo_color')->default('#e11d48');
            $table->string('logo_text_color')->default('#ffffff');
            $table->string('cover_color')->default('#27272a');
            $table->string('privacy')->default('private');
            $table->foreignId('created_by')->nullable()->constrained('drivers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};