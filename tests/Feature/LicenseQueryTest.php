<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseQueryTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brandA;

    protected Brand $brandB;

    protected function setUp(): void
    {
        parent::setUp();

        // Brand A
        $this->brandA = Brand::create([
            'name' => 'RankMath',
            'code' => 'rankmath',
        ]);

        $productA = Product::create([
            'brand_id' => $this->brandA->id,
            'code' => 'rankmath',
            'name' => 'RankMath Core',
        ]);

        $licenseKeyA = LicenseKey::create([
            'brand_id' => $this->brandA->id,
            'customer_email' => 'user@example.com',
            'key' => 'RM-AAAA-BBBB',
        ]);

        License::create([
            'license_key_id' => $licenseKeyA->id,
            'product_id' => $productA->id,
            'status' => 'valid',
            'expires_at' => now()->addYear(),
        ]);

        // Brand B
        $this->brandB = Brand::create([
            'name' => 'WP Rocket',
            'code' => 'wp_rocket',
        ]);

        $productB = Product::create([
            'brand_id' => $this->brandB->id,
            'code' => 'wp_rocket',
            'name' => 'WP Rocket',
        ]);

        $licenseKeyB = LicenseKey::create([
            'brand_id' => $this->brandB->id,
            'customer_email' => 'user@example.com',
            'key' => 'WR-CCCC-DDDD',
        ]);

        License::create([
            'license_key_id' => $licenseKeyB->id,
            'product_id' => $productB->id,
            'status' => 'cancelled',
            'expires_at' => now()->addMonths(6),
        ]);
    }

    public function test_brand_can_list_licenses_across_all_brands_by_email(): void
    {
        $response = $this->getJson(
            "/api/brands/{$this->brandA->id}/licenses?email=user@example.com"
        );

        $response->assertOk()
            ->assertJson([
                'customer_email' => 'user@example.com',
            ])
            ->assertJsonCount(2, 'licenses');
    }

    public function test_unknown_email_returns_empty_license_list(): void
    {
        $response = $this->getJson(
            "/api/brands/{$this->brandA->id}/licenses?email=unknown@example.com"
        );

        $response->assertOk()
            ->assertJson([
                'customer_email' => 'unknown@example.com',
                'licenses' => [],
            ]);
    }

    public function test_brand_context_does_not_filter_cross_brand_results(): void
    {
        $response = $this->getJson(
            "/api/brands/{$this->brandB->id}/licenses?email=user@example.com"
        );

        $response->assertOk()
            ->assertJsonCount(2, 'licenses');
    }
}
