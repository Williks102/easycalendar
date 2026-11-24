<?php

namespace Tests\Feature;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Professional $pro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pro = Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama']);
        $this->actingAs($this->pro, 'sanctum');
    }

    public function test_weekly_rules_are_replaced_as_a_whole(): void
    {
        $this->putJson('/api/v1/availability', ['rules' => [
            ['weekday' => 0, 'start_time' => '09:00', 'end_time' => '12:00'],
            ['weekday' => 0, 'start_time' => '14:00', 'end_time' => '18:00'],
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
        ]])->assertOk()->assertJsonCount(3, 'rules');

        $this->putJson('/api/v1/availability', ['rules' => [
            ['weekday' => 5, 'start_time' => '10:00', 'end_time' => '16:00'],
        ]])->assertOk()->assertJsonCount(1, 'rules')->assertJsonPath('rules.0.weekday', 5);

        $this->putJson('/api/v1/availability', ['rules' => []])->assertOk()->assertJsonCount(0, 'rules');
    }

    public function test_invalid_ranges_are_refused_and_nothing_is_changed(): void
    {
        $this->putJson('/api/v1/availability', ['rules' => [['weekday' => 0, 'start_time' => '09:00', 'end_time' => '12:00']]])->assertOk();

        $this->putJson('/api/v1/availability', ['rules' => [['weekday' => 7, 'start_time' => '09:00', 'end_time' => '12:00']]])->assertStatus(422);
        $this->putJson('/api/v1/availability', ['rules' => [['weekday' => 1, 'start_time' => '12:00', 'end_time' => '09:00']]])->assertStatus(422);
        $this->putJson('/api/v1/availability', ['rules' => [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
            ['weekday' => 1, 'start_time' => '11:00', 'end_time' => '14:00'],
        ]])->assertStatus(422);

        $this->getJson('/api/v1/availability')->assertJsonCount(1, 'rules')->assertJsonPath('rules.0.weekday', 0);
    }

    public function test_exceptions_one_per_day_and_deletable(): void
    {
        $this->postJson('/api/v1/availability/exceptions', ['date' => '2026-12-25', 'type' => 'closed'])->assertCreated();
        $id = $this->postJson('/api/v1/availability/exceptions', [
            'date' => '2026-12-25', 'type' => 'custom', 'start_time' => '10:00', 'end_time' => '13:00',
        ])->assertCreated()->assertJsonPath('type', 'custom')->json('id');

        $this->getJson('/api/v1/availability')->assertJsonCount(1, 'exceptions');
        $this->deleteJson("/api/v1/availability/exceptions/$id")->assertNoContent();
        $this->getJson('/api/v1/availability')->assertJsonCount(0, 'exceptions');
    }

    public function test_custom_exception_needs_times(): void
    {
        $this->postJson('/api/v1/availability/exceptions', ['date' => '2026-12-25', 'type' => 'custom'])
            ->assertStatus(422)->assertJsonValidationErrors(['start_time', 'end_time']);
        $this->postJson('/api/v1/availability/exceptions', ['date' => '2026-12-25', 'type' => 'custom', 'start_time' => '13:00', 'end_time' => '10:00'])
            ->assertStatus(422);
    }

    public function test_cannot_delete_another_professionals_exception(): void
    {
        $other = Professional::create(['phone' => '+2250700000002', 'name' => 'Bob', 'slug' => 'bob']);
        $exception = $other->availabilityExceptions()->create(['date' => '2026-12-25', 'type' => 'closed']);

        $this->deleteJson("/api/v1/availability/exceptions/{$exception->id}")->assertNotFound();
    }
}
