<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Professional;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garde-fous posés en base (voir docs/DATABASE.md) : anti-chevauchement,
 * idempotence des paiements, statuts et formats.
 */
class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private Professional $pro;

    private Service $service;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pro = Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama']);
        $this->service = $this->pro->services()->create([
            'name' => 'Coupe', 'duration_min' => 60, 'price_xof' => 10000, 'deposit_xof' => 3000, 'buffer_min' => 15,
        ]);
        $this->customer = Customer::create(['name' => 'Koné Awa', 'phone_whatsapp' => '+2250700000000']);
    }

    private function book(string $startsAt, string $status = Booking::PENDING_PAYMENT, ?string $token = null): Booking
    {
        $start = CarbonImmutable::parse($startsAt, 'UTC');
        $end = $start->addMinutes($this->service->duration_min);

        return Booking::create([
            'professional_id' => $this->pro->id,
            'service_id' => $this->service->id,
            'customer_id' => $this->customer->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'blocked_until' => $end->addMinutes($this->service->buffer_min),
            'status' => $status,
            'hold_expires_at' => $status === Booking::PENDING_PAYMENT ? $start->subDay() : null,
            'cancel_token' => $token ?? bin2hex(random_bytes(20)),
        ]);
    }

    public function test_overlapping_active_bookings_are_refused(): void
    {
        $this->book('2026-12-02 09:30:00');

        $this->expectException(QueryException::class);
        $this->book('2026-12-02 10:30:00'); // tombe dans le temps tampon
    }

    public function test_booking_right_after_the_buffer_is_accepted(): void
    {
        $this->book('2026-12-02 09:30:00');

        $second = $this->book('2026-12-02 10:45:00');

        $this->assertNotNull($second->id);
    }

    public function test_slot_is_free_again_once_the_booking_is_cancelled(): void
    {
        $first = $this->book('2026-12-02 09:30:00', Booking::CONFIRMED);
        $first->update(['status' => Booking::CANCELLED_BY_PRO]);

        $this->assertNotNull($this->book('2026-12-02 09:30:00')->id);
    }

    public function test_unknown_booking_status_is_refused(): void
    {
        $this->expectException(QueryException::class);
        $this->book('2026-12-02 09:30:00', 'paid');
    }

    public function test_provider_ref_is_unique(): void
    {
        $booking = $this->book('2026-12-02 09:30:00');
        $payment = fn () => Payment::create([
            'booking_id' => $booking->id, 'amount_xof' => 3000, 'operator' => 'wave',
            'provider' => 'fake', 'provider_ref' => 'ref-1',
        ]);
        $payment();

        $this->expectException(QueryException::class);
        $payment();
    }

    public function test_deposit_cannot_exceed_price(): void
    {
        $this->expectException(QueryException::class);
        $this->pro->services()->create([
            'name' => 'X', 'duration_min' => 30, 'price_xof' => 1000, 'deposit_xof' => 2000,
        ]);
    }

    public function test_phone_must_be_e164(): void
    {
        $this->expectException(QueryException::class);
        Customer::create(['name' => 'A', 'phone_whatsapp' => '0700000000']);
    }
}
