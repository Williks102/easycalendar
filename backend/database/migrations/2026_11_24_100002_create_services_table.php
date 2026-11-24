<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->string('name', 120);
            $table->integer('duration_min');
            $table->integer('price_xof');
            $table->integer('deposit_xof');
            $table->integer('buffer_min')->default(0);
            $table->boolean('is_active')->default(true);
            $table->index('professional_id', 'services_professional_idx');
        });

        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_positive CHECK (duration_min > 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_nonnegative CHECK (price_xof >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_deposit_range CHECK (deposit_xof >= 0 AND deposit_xof <= price_xof)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_buffer_nonnegative CHECK (buffer_min >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
