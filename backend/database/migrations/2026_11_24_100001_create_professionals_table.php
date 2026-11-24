<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('password_hash', 255)->nullable();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();
            $table->string('business_name', 160)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('timezone', 64)->default('Africa/Abidjan');
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('payout_operator', 20)->nullable();
            $table->string('payout_phone', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE professionals ADD CONSTRAINT professionals_phone_e164 CHECK (phone ~ '^\\+[1-9][0-9]{7,14}$')");
        DB::statement("ALTER TABLE professionals ADD CONSTRAINT professionals_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE professionals ADD CONSTRAINT professionals_payout_operator_valid CHECK (payout_operator IS NULL OR payout_operator IN ('wave', 'orange', 'mtn', 'moov'))");
        DB::statement("ALTER TABLE professionals ADD CONSTRAINT professionals_status_valid CHECK (status IN ('active', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
