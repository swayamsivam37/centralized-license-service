<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;

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

        $licenseKey = LicenseKey::create([
            'brand_id' => $this->brand->id,
            'customer_email' => 'user@example.com',
            'key' => 'TEST-KEY-1234',
        ]);

        $this->license = License::create([
            'license_key_id' => $licenseKey->id,
            'product_id' => $product->id,
            'status' => 'valid',
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_brand_can_suspend_a_license(): void
    {
        $response = $this->patchJson(
            "/api/brands/{$this->brand->id}/licenses/{$this->license->id}",
            ['action' => 'suspend']
        );

        $response->assertOk()
            ->assertJson([
                'status' => 'suspended',
            ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'status' => 'suspended',
        ]);
    }

    public function test_brand_can_resume_a_suspended_license(): void
    {
        $this->license->update(['status' => 'suspended']);

        $response = $this->patchJson(
            "/api/brands/{$this->brand->id}/licenses/{$this->license->id}",
            ['action' => 'resume']
        );

        $response->assertOk()
            ->assertJson([
                'status' => 'valid',
            ]);
    }

    public function test_brand_can_renew_a_license(): void
    {
        $response = $this->patchJson(
            "/api/brands/{$this->brand->id}/licenses/{$this->license->id}",
            [
                'action' => 'renew',
                'expires_at' => '2028-01-01',
            ]
        );

        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'id' => $this->license->id,
            'expires_at' => '2028-01-01 00:00:00',
        ]);
    }

    public function test_brand_can_cancel_a_license(): void
    {
        $response = $this->patchJson(
            "/api/brands/{$this->brand->id}/licenses/{$this->license->id}",
            ['action' => 'cancel']
        );

        $response->assertOk()
            ->assertJson([
                'status' => 'cancelled',
            ]);
    }

    public function test_cancelled_license_cannot_be_modified(): void
    {
        $this->license->update(['status' => 'cancelled']);

        $response = $this->patchJson(
            "/api/brands/{$this->brand->id}/licenses/{$this->license->id}",
            ['action' => 'suspend']
        );

        $response->assertStatus(500);
    }

    public function test_brand_cannot_modify_license_of_another_brand(): void
    {
        $otherBrand = Brand::create([
            'name' => 'WP Rocket',
            'code' => 'wp_rocket',
        ]);

        $response = $this->patchJson(
            "/api/brands/{$otherBrand->id}/licenses/{$this->license->id}",
            ['action' => 'suspend']
        );

        $response->assertStatus(500);
    }
}
