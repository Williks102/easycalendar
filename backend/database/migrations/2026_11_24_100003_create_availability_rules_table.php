<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // weekday : 0 = lundi ... 6 = dimanche
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->index(['professional_id', 'weekday'], 'availability_rules_professional_weekday_idx');
        });

        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_weekday_range CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_time_order CHECK (end_time > start_time)');
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
