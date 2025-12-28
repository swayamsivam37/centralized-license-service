<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\LicenseKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandLicenseProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create([
            'name' => 'RankMath',
            'code' => 'rankmath',
        ]);

        $this->product = Product::create([
            'brand_id' => $this->brand->id,
            'code' => 'rankmath',
            'name' => 'RankMath Core',
        ]);

        Product::create([
            'brand_id' => $this->brand->id,
            'code' => 'content_ai',
            'name' => 'Content AI',
        ]);
    }

    public function test_brand_can_provision_license_key_with_license(): void
    {
        $response = $this->postJson(
            "/api/brands/{$this->brand->id}/license-keys",
            [
                'customer_email' => 'user@example.com',
                'licenses' => [
                    [
                        'product_code' => 'rankmath',
                        'expires_at' => '2026-01-01',
                    ],
                ],
            ]
        );

        $response->assertCreated()
            ->assertJsonStructure([
                'license_key' => ['id', 'key', 'customer_email'],
                'licenses' => [
                    [
                        'product',
                        'status',
                        'expires_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('license_keys', [
            'brand_id' => $this->brand->id,
            'customer_email' => 'user@example.com',
        ]);

        $this->assertDatabaseHas('licenses', [
            'status' => 'valid',
        ]);
    }

    public function test_brand_can_add_license_to_existing_license_key(): void
    {
        $firstResponse = $this->postJson(
            "/api/brands/{$this->brand->id}/license-keys",
            [
                'customer_email' => 'user@example.com',
                'licenses' => [
                    [
                        'product_code' => 'rankmath',
                        'expires_at' => '2026-01-01',
                    ],
                ],
            ]
        );

        $licenseKeyId = $firstResponse->json('license_key.id');

        $secondResponse = $this->postJson(
            "/api/brands/{$this->brand->id}/license-keys",
            [
                'customer_email' => 'user@example.com',
                'existing_license_key_id' => $licenseKeyId,
                'licenses' => [
                    [
                        'product_code' => 'content_ai',
                        'expires_at' => '2026-01-01',
                    ],
                ],
            ]
        );

        $secondResponse->assertCreated();

        $this->assertEquals(
            $licenseKeyId,
            $secondResponse->json('license_key.id')
        );

        $this->assertDatabaseCount('licenses', 2);
    }

    public function test_cannot_use_license_key_from_another_brand(): void
    {
        $otherBrand = Brand::create([
            'name' => 'WP Rocket',
            'code' => 'wp_rocket',
        ]);

        $licenseKey = LicenseKey::create([
            'brand_id' => $otherBrand->id,
            'customer_email' => 'user@example.com',
            'key' => 'FOREIGN-KEY',
        ]);

        $response = $this->postJson(
            "/api/brands/{$this->brand->id}/license-keys",
            [
                'customer_email' => 'user@example.com',
                'existing_license_key_id' => $licenseKey->id,
                'licenses' => [
                    [
                        'product_code' => 'rankmath',
                        'expires_at' => '2026-01-01',
                    ],
                ],
            ]
        );

        $response->assertStatus(500);
    }
}
