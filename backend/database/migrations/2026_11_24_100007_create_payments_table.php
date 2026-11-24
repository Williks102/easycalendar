<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // provider_ref unique : rend les webhooks idempotents (NULL tant que l'agrégateur n'a pas répondu).
        // Plusieurs paiements possibles par rendez-vous (nouvel essai après un échec).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->integer('amount_xof');
            $table->string('operator', 20);
            $table->string('provider', 40);
            $table->string('provider_ref', 120)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->jsonb('raw_payload')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('booking_id', 'payments_booking_idx');
            $table->index('status', 'payments_status_idx');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount_xof > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_operator_valid CHECK (operator IN ('wave', 'orange', 'mtn', 'moov'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_valid CHECK (status IN ('pending', 'succeeded', 'failed', 'refund_due', 'refunded'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
