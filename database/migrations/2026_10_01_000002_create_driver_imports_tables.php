<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A staging record for a CSV upload. Nothing here mutates drivers or
        // race results: the rows are held here until an administrator reviews
        // the preview and explicitly confirms the import, and rows that match
        // an existing driver are never overwritten automatically.
        Schema::create('driver_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('status')->default('preview');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_import_id')->constrained('driver_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');

            // Exactly what the CSV said, so a rejected row can be reviewed.
            $table->string('raw_name');
            $table->string('raw_email')->nullable();
            $table->string('raw_phone')->nullable();
            $table->string('raw_event_label')->nullable();
            $table->string('raw_finish_position')->nullable();
            $table->string('raw_points_displayed')->nullable();

            // How the row was resolved during preview.
            $table->string('match_status')->default('unmatched');
            $table->foreignId('matched_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->decimal('match_score', 5, 4)->nullable();
            $table->json('issues')->nullable();

            // Null until the import is confirmed. Historical results are never
            // written by this workflow.
            $table->foreignId('created_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            $table->index(['driver_import_id', 'row_number']);
            $table->index(['driver_import_id', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_import_rows');
        Schema::dropIfExists('driver_imports');
    }
};
