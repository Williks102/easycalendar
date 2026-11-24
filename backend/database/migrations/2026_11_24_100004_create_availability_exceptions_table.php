<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une seule exception par professionnel et par jour.
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unique(['professional_id', 'date'], 'availability_exceptions_day_unique');
        });

        DB::statement("ALTER TABLE availability_exceptions ADD CONSTRAINT availability_exceptions_type_valid CHECK (type IN ('closed', 'custom'))");
        DB::statement("ALTER TABLE availability_exceptions ADD CONSTRAINT availability_exceptions_times_valid CHECK (
            (type = 'closed' AND start_time IS NULL AND end_time IS NULL)
            OR (type = 'custom' AND start_time IS NOT NULL AND end_time IS NOT NULL AND end_time > start_time)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
