<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable so every existing race keeps a null label and nothing about
        // its historical results changes. When set, the label is what the
        // leaderboard shows as the event column heading (e.g. PITSTOP, VIRAJ,
        // FNF) instead of the internal race name.
        Schema::table('races', function (Blueprint $table): void {
            $table->string('event_label')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('races', function (Blueprint $table): void {
            $table->dropColumn('event_label');
        });
    }
};
