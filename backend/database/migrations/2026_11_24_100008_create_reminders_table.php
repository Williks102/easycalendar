<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('kind', 30);
            $table->timestampTz('scheduled_at');
            $table->timestampTz('sent_at')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->string('provider_message_id', 120)->nullable();
            $table->text('error')->nullable();
            // Envoi des rappels (chaque minute)
            $table->index(['status', 'scheduled_at'], 'reminders_status_scheduled_idx');
            $table->index('booking_id', 'reminders_booking_idx');
        });

        DB::statement("ALTER TABLE reminders ADD CONSTRAINT reminders_channel_valid CHECK (channel IN ('whatsapp', 'sms'))");
        DB::statement("ALTER TABLE reminders ADD CONSTRAINT reminders_kind_valid CHECK (kind IN (
            'confirmation', 'pro_new_booking', 'reminder_d1',
            'reminder_h2', 'cancellation', 'payment_expired'
        ))");
        DB::statement("ALTER TABLE reminders ADD CONSTRAINT reminders_status_valid CHECK (status IN ('scheduled', 'sent', 'delivered', 'failed', 'cancelled'))");
        // Webhook de statut WhatsApp : retrouver le rappel par l'identifiant du message
        DB::statement('CREATE INDEX reminders_provider_message_idx ON reminders (provider_message_id) WHERE provider_message_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
