<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone_whatsapp', 20);
            $table->timestampTz('created_at')->useCurrent();
            $table->index('phone_whatsapp', 'customers_phone_idx');
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_phone_e164 CHECK (phone_whatsapp ~ '^\\+[1-9][0-9]{7,14}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
