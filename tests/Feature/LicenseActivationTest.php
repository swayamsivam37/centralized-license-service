<?php

namespace Tests\Feature;

use App\Models\Activation;
use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseActivationTest extends TestCase
{
    use RefreshDatabase;

    protected LicenseKey $licenseKey;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Brand::create([
            'name' => 'RankMath',
            'code' => 'rankmath',
        ]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'code' => 'rankmath',
            'name' => 'RankMath Core',
        ]);

        $this->licenseKey = LicenseKey::create([
            'brand_id' => $brand->id,
            'customer_email' => 'user@example.com',
            'key' => 'TEST-ACTIVATION-KEY',
        ]);

        License::create([
            'license_key_id' => $this->licenseKey->id,
            'product_id' => $product->id,
            'status' => 'valid',
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_end_user_can_activate_license_key(): void
    {
        $response = $this->postJson('/api/activate', [
            'license_key' => 'TEST-ACTIVATION-KEY',
            'instance_id' => 'https://example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'active',
            ])
            ->assertJsonStructure([
                'licenses' => [
                    [
                        'product_code',
                        'status',
                        'expires_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('activations', [
            'license_key_id' => $this->licenseKey->id,
            'instance_id' => 'https://example.com',
        ]);
    }

    public function test_activation_is_idempotent_for_same_instance(): void
    {
        $payload = [
            'license_key' => 'TEST-ACTIVATION-KEY',
            'instance_id' => 'https://example.com',
        ];

        $this->postJson('/api/activate', $payload)->assertOk();
        $this->postJson('/api/activate', $payload)->assertOk();

        $this->assertEquals(
            1,
            Activation::where('license_key_id', $this->licenseKey->id)
                ->where('instance_id', 'https://example.com')
                ->count()
        );
    }

    public function test_activation_fails_for_invalid_license_key(): void
    {
        $response = $this->postJson('/api/activate', [
            'license_key' => 'INVALID-KEY',
            'instance_id' => 'https://example.com',
        ]);

        $response->assertStatus(500);
    }

    public function test_activation_fails_when_no_valid_licenses_exist(): void
    {
        License::query()->update([
            'status' => 'cancelled',
        ]);

        $response = $this->postJson('/api/activate', [
            'license_key' => 'TEST-ACTIVATION-KEY',
            'instance_id' => 'https://example.com',
        ]);

        $response->assertStatus(500);
    }
}
