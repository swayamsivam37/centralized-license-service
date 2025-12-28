<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\LicenseKey;
use App\Models\License;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service responsible for provisioning license keys and licenses for a brand.
 *
 * This service encapsulates all business rules related to:
 * - creating or reusing license keys
 * - validating brand ownership
 * - attaching product-specific licenses to a license key
 *
 * It is intentionally decoupled from HTTP concerns and authentication,
 * allowing it to be reused by controllers, jobs, or other services.
 */
class LicenseProvisioningService
{
    /**
     * Provision a license key and associated licenses for a customer.
     *
     * If an existing license key is provided, the licenses will be attached
     * to that key (after validating brand ownership). Otherwise, a new
     * license key will be generated.
     *
     * The operation is executed inside a database transaction to ensure
     * atomicity: either all licenses are provisioned successfully, or none
     * are.
     *
     * @param Brand $brand
     *   The brand on whose behalf the provisioning is performed.
     *
     * @param string $customerEmail
     *   The email address identifying the customer. The service does not
     *   own user identity and treats this value as an external reference.
     *
     * @param array $licensesData
     *   A list of licenses to provision. Each item must contain:
     *   - product_code (string)
     *   - expires_at (datetime string)
     *
     * @param LicenseKey|null $existingLicenseKey
     *   Optional existing license key to which new licenses should be
     *   attached. Must belong to the same brand.
     *
     * @return LicenseKey
     *   The provisioned license key with its associated licenses loaded.
     *
     * @throws InvalidArgumentException
     *   If the license key does not belong to the brand or if a product
     *   cannot be resolved for the brand.
     */
    public function provision(
        Brand $brand,
        string $customerEmail,
        array $licensesData,
        ?LicenseKey $existingLicenseKey = null
    ): LicenseKey {
        return DB::transaction(function () use (
            $brand,
            $customerEmail,
            $licensesData,
            $existingLicenseKey
        ) {
            $licenseKey = $existingLicenseKey
                ? $this->validateExistingLicenseKey($brand, $existingLicenseKey)
                : $this->createLicenseKey($brand, $customerEmail);

            foreach ($licensesData as $licenseData) {
                $this->attachLicense(
                    $brand,
                    $licenseKey,
                    $licenseData
                );
            }

            return $licenseKey->load('licenses.product');
        });
    }

    /**
     * Create a new license key for a customer under a specific brand.
     *
     * @param Brand $brand
     *   The brand owning the license key.
     *
     * @param string $customerEmail
     *   The customer email associated with the license key.
     *
     * @return LicenseKey
     *   The newly created license key.
     */
    protected function createLicenseKey(
        Brand $brand,
        string $customerEmail
    ): LicenseKey {
        return LicenseKey::create([
            'brand_id' => $brand->id,
            'customer_email' => $customerEmail,
            'key' => $this->generateLicenseKey(),
        ]);
    }

    /**
     * Validate that an existing license key belongs to the given brand.
     *
     * This prevents cross-brand license key reuse, which is explicitly
     * disallowed by the domain model.
     *
     * @param Brand $brand
     *   The brand performing the provisioning.
     *
     * @param LicenseKey $licenseKey
     *   The license key to validate.
     *
     * @return LicenseKey
     *   The validated license key.
     *
     * @throws InvalidArgumentException
     *   If the license key does not belong to the brand.
     */
    protected function validateExistingLicenseKey(
        Brand $brand,
        LicenseKey $licenseKey
    ): LicenseKey {
        if ($licenseKey->brand_id !== $brand->id) {
            throw new InvalidArgumentException(
                'License key does not belong to this brand.'
            );
        }

        return $licenseKey;
    }

    /**
     * Attach a product license to a license key.
     *
     * The product must exist and belong to the given brand. If a license
     * for the same product is already attached to the license key, the
     * operation is ignored to avoid duplication.
     *
     * @param Brand $brand
     *   The brand owning the product.
     *
     * @param LicenseKey $licenseKey
     *   The license key to attach the license to.
     *
     * @param array $licenseData
     *   License configuration data containing:
     *   - product_code (string)
     *   - expires_at (datetime string)
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *   If the product cannot be found for the brand.
     */
    protected function attachLicense(
        Brand $brand,
        LicenseKey $licenseKey,
        array $licenseData
    ): void {
        $product = Product::where('brand_id', $brand->id)
            ->where('code', $licenseData['product_code'])
            ->first();

        if (! $product) {
            throw new InvalidArgumentException(
                "Product {$licenseData['product_code']} not found for this brand."
            );
        }

        License::firstOrCreate(
            [
                'license_key_id' => $licenseKey->id,
                'product_id' => $product->id,
            ],
            [
                'status' => 'valid',
                'expires_at' => $licenseData['expires_at'],
            ]
        );
    }

    /**
     * Generate a new opaque license key string.
     *
     * The generated key is intended to be non-guessable and user-friendly,
     * but no cryptographic guarantees are made at this stage. This strategy
     * can be replaced later without impacting consumers.
     *
     * @return string
     *   The generated license key.
     */
    protected function generateLicenseKey(): string
    {
        return strtoupper(Str::random(4)) . '-' .
            strtoupper(Str::random(4)) . '-' .
            strtoupper(Str::random(4));
    }
}