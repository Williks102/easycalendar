<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // blocked_until = ends_at + buffer_min du service : c'est lui qui sert au chevauchement.
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained('professionals');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('customer_id')->constrained('customers');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestampTz('blocked_until');
            $table->string('status', 30)->default('pending_payment');
            $table->timestampTz('hold_expires_at')->nullable();
            $table->string('cancel_token', 100)->unique();
            $table->string('source', 30)->default('public_page');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['professional_id', 'starts_at'], 'bookings_professional_starts_idx');
        });

        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_valid CHECK (status IN (
            'pending_payment', 'confirmed', 'cancelled_by_customer',
            'cancelled_by_pro', 'completed', 'no_show', 'expired'
        ))");
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_time_order CHECK (ends_at > starts_at AND blocked_until >= ends_at)');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_hold_required CHECK (status <> 'pending_payment' OR hold_expires_at IS NOT NULL)");

        // Interdit deux rendez-vous actifs qui se chevauchent pour un même professionnel.
        // Ne connaît pas hold_expires_at : avant d'insérer, la transaction de création passe
        // en 'expired' les pending_payment périmés du même professionnel (voir docs/DATABASE.md).
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap EXCLUDE USING gist (
            professional_id WITH =,
            tstzrange(starts_at, blocked_until) WITH &&
        ) WHERE (status IN ('pending_payment', 'confirmed'))");

        // Tâche d'expiration des créneaux (chaque minute)
        DB::statement("CREATE INDEX bookings_hold_expiry_idx ON bookings (hold_expires_at) WHERE status = 'pending_payment'");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
