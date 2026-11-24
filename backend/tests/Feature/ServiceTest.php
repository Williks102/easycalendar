<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private Professional $pro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pro = Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama']);
    }

    private function payload(array $override = []): array
    {
        return array_merge(['name' => 'Coupe', 'duration_min' => 60, 'price_xof' => 10000, 'deposit_xof' => 3000, 'buffer_min' => 15], $override);
    }

    public function test_crud_cycle(): void
    {
        $this->actingAs($this->pro, 'sanctum');

        $id = $this->postJson('/api/v1/services', $this->payload())->assertCreated()
            ->assertJsonPath('price_xof', 10000)->json('id');

        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(1);
        $this->patchJson("/api/v1/services/$id", ['price_xof' => 12000, 'is_active' => false])
            ->assertOk()->assertJsonPath('price_xof', 12000)->assertJsonPath('is_active', false);
        $this->deleteJson("/api/v1/services/$id")->assertNoContent();
        $this->getJson('/api/v1/services')->assertJsonCount(0);
    }

    public function test_amounts_are_integers_and_deposit_is_capped_by_price(): void
    {
        $this->actingAs($this->pro, 'sanctum');

        $this->postJson('/api/v1/services', $this->payload(['price_xof' => 1000.5]))
            ->assertStatus(422)->assertJsonValidationErrors('price_xof');
        $this->postJson('/api/v1/services', $this->payload(['deposit_xof' => 20000]))
            ->assertStatus(422)->assertJsonValidationErrors('deposit_xof');

        $id = $this->postJson('/api/v1/services', $this->payload())->json('id');
        $this->patchJson("/api/v1/services/$id", ['price_xof' => 2000])
            ->assertStatus(422)->assertJsonValidationErrors('deposit_xof');
    }

    public function test_a_professional_cannot_touch_another_ones_service(): void
    {
        $other = Professional::create(['phone' => '+2250700000002', 'name' => 'Bob', 'slug' => 'bob']);
        $service = $other->services()->create($this->payload());

        $this->actingAs($this->pro, 'sanctum');
        $this->getJson("/api/v1/services/{$service->id}")->assertNotFound();
        $this->patchJson("/api/v1/services/{$service->id}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/v1/services/{$service->id}")->assertNotFound();
        $this->getJson('/api/v1/services')->assertJsonCount(0);
    }

    public function test_service_with_bookings_cannot_be_deleted(): void
    {
        $service = $this->pro->services()->create($this->payload());
        $customer = Customer::create(['name' => 'Awa', 'phone_whatsapp' => '+2250700000000']);
        Booking::create([
            'professional_id' => $this->pro->id, 'service_id' => $service->id, 'customer_id' => $customer->id,
            'starts_at' => '2026-12-02 09:30:00', 'ends_at' => '2026-12-02 10:30:00', 'blocked_until' => '2026-12-02 10:45:00',
            'status' => 'completed', 'cancel_token' => 'tok',
        ]);

        $this->actingAs($this->pro, 'sanctum')->deleteJson("/api/v1/services/{$service->id}")->assertStatus(409);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/services')->assertUnauthorized();
    }
}
