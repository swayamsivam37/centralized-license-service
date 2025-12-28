<?php

namespace Tests\Feature;

use App\Models\Activation;
use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

    protected LicenseKey $licenseKey;

    protected License $license;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'RankMath',
            'code' => 'rankmath',
        ]);

        $product = Product::create([
            'brand_id' => $this->brand->id,
            'code' => 'rankmath',
            'name' => 'RankMath Core',
        ]);

        $this->licenseKey = LicenseKey::create([
            'brand_id' => $this->brand->id,
            'customer_email' => 'user@example.com',
            'key' => 'TEST-KEY-VALIDATE',
        ]);

        $this->license = License::create([
            'license_key_id' => $this->licenseKey->id,
            'product_id' => $product->id,
            'status' => 'valid',
            'expires_at' => now()->addYear(),
        ]);

        // Optional: simulate one activation
        Activation::create([
            'license_key_id' => $this->licenseKey->id,
            'instance_id' => 'https://example.com',
            'activated_at' => now(),
        ]);
    }

    public function test_valid_license_key_returns_entitlements(): void
    {
        $response = $this->postJson('/api/validate', [
            'license_key' => 'TEST-KEY-VALIDATE',
            'instance_id' => 'https://example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'valid',
            ])
            ->assertJsonStructure([
                'licenses' => [
                    [
                        'product_code',
                        'status',
                        'expires_at',
                    ],
                ],
                'seats' => [
                    'used',
                    'remaining',
                ],
            ]);
    }

    public function test_invalid_license_key_returns_not_found(): void
    {
        $response = $this->postJson('/api/validate', [
            'license_key' => 'INVALID-KEY',
        ]);

        $response->assertStatus(404);
    }

    public function test_expired_license_is_not_returned(): void
    {
        $this->license->update([
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => 'TEST-KEY-VALIDATE',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'invalid',
                'licenses' => [],
            ]);
    }

    public function test_suspended_license_is_not_returned(): void
    {
        $this->license->update([
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => 'TEST-KEY-VALIDATE',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'invalid',
                'licenses' => [],
            ]);
    }

    public function test_multiple_licenses_under_same_key_are_returned(): void
    {
        $addonProduct = Product::create([
            'brand_id' => $this->brand->id,
            'code' => 'content-ai',
            'name' => 'Content AI',
        ]);

        License::create([
            'license_key_id' => $this->licenseKey->id,
            'product_id' => $addonProduct->id,
            'status' => 'valid',
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->postJson('/api/validate', [
            'license_key' => 'TEST-KEY-VALIDATE',
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'licenses');
    }
}
