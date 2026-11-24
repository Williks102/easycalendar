<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private object $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = new class implements OtpSender
        {
            public array $codes = [];

            public function send(string $phone, string $code): void
            {
                $this->codes[$phone] = $code;
            }
        };
        $this->app->instance(OtpSender::class, $this->sender);
    }

    private function requestCode(string $phone): string
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone])->assertStatus(202);

        return $this->sender->codes[$phone];
    }

    public function test_registration_creates_account_and_returns_token(): void
    {
        $code = $this->requestCode('+2250700000001');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+2250700000001', 'code' => $code,
            'name' => 'Ama', 'slug' => 'salon-ama', 'password' => 'motdepasse',
        ])->assertCreated()->assertJsonStructure(['token', 'professional' => ['id', 'slug']]);

        $this->assertDatabaseHas('professionals', ['phone' => '+2250700000001', 'slug' => 'salon-ama']);
        $this->assertStringNotContainsString('password', json_encode($response->json()));

        $this->withToken($response->json('token'))->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('slug', 'salon-ama');
    }

    public function test_login_of_existing_account_returns_200(): void
    {
        Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama']);
        $code = $this->requestCode('+2250700000001');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+2250700000001', 'code' => $code])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_wrong_code_is_refused_and_creates_nothing(): void
    {
        $this->requestCode('+2250700000001');

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+2250700000001', 'code' => '000000', 'name' => 'Ama', 'slug' => 'salon-ama',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('professionals', 0);
    }

    public function test_code_is_single_use(): void
    {
        $code = $this->requestCode('+2250700000001');
        $payload = ['phone' => '+2250700000001', 'code' => $code, 'name' => 'Ama', 'slug' => 'salon-ama'];

        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertCreated();
        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertStatus(422);
    }

    public function test_code_is_blocked_after_too_many_wrong_attempts(): void
    {
        $code = $this->requestCode('+2250700000001');
        $wrong = $code === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => '+2250700000001', 'code' => $wrong, 'name' => 'Ama', 'slug' => 'salon-ama',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+2250700000001', 'code' => $code, 'name' => 'Ama', 'slug' => 'salon-ama',
        ])->assertStatus(422);
    }

    public function test_new_account_requires_name_and_unique_slug(): void
    {
        Professional::create(['phone' => '+2250700000009', 'name' => 'Autre', 'slug' => 'salon-ama']);
        $code = $this->requestCode('+2250700000001');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+2250700000001', 'code' => $code])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'slug']);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+2250700000001', 'code' => $code, 'name' => 'Ama', 'slug' => 'salon-ama',
        ])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_phone_must_be_e164(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '0700000000'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_otp_request_is_rate_limited_per_phone(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => '+2250700000001'])->assertStatus(202);
        }

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+2250700000001'])->assertStatus(429);
    }

    public function test_suspended_account_cannot_use_the_api(): void
    {
        $pro = Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama', 'status' => 'suspended']);
        $token = $pro->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_me_requires_authentication_and_logout_revokes_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();

        $pro = Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama']);
        $token = $pro->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_profile_update_cannot_change_status_or_phone(): void
    {
        $pro = Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama']);

        $this->actingAs($pro, 'sanctum')->patchJson('/api/v1/me', [
            'business_name' => 'Salon Ama', 'payout_operator' => 'wave', 'payout_phone' => '+2250700000002',
            'status' => 'suspended', 'phone' => '+2250799999999',
        ])->assertOk()->assertJsonPath('business_name', 'Salon Ama');

        $pro->refresh();
        $this->assertSame('active', $pro->status);
        $this->assertSame('+2250700000001', $pro->phone);
        $this->assertSame('wave', $pro->payout_operator);
    }

    public function test_profile_rejects_unknown_operator_and_taken_slug(): void
    {
        Professional::create(['phone' => '+2250700000009', 'name' => 'Autre', 'slug' => 'pris']);
        $pro = Professional::create(['phone' => '+2250700000001', 'name' => 'Ama', 'slug' => 'salon-ama']);

        $this->actingAs($pro, 'sanctum')->patchJson('/api/v1/me', ['payout_operator' => 'paypal', 'slug' => 'pris'])
            ->assertStatus(422)->assertJsonValidationErrors(['payout_operator', 'slug']);
    }
}
