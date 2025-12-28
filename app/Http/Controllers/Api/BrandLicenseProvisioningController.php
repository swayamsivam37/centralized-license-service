<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\LicenseKey;
use App\Services\LicenseProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandLicenseProvisioningController extends Controller
{
    public function __construct(
        protected LicenseProvisioningService $provisioningService
    ) {}

    /**
     * Provision a license key and licenses for a customer.
     *
     * This endpoint is intended for trusted brand systems only.
     */
    public function store(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'customer_email' => ['required', 'email'],
            'licenses' => ['required', 'array', 'min:1'],
            'licenses.*.product_code' => ['required', 'string'],
            'licenses.*.expires_at' => ['required', 'date'],
            'existing_license_key_id' => ['nullable', 'integer'],
        ]);

        $existingLicenseKey = null;

        if (!empty($validated['existing_license_key_id'])) {
            $existingLicenseKey = LicenseKey::findOrFail(
                $validated['existing_license_key_id']
            );
        }

        $licenseKey = $this->provisioningService->provision(
            $brand,
            $validated['customer_email'],
            $validated['licenses'],
            $existingLicenseKey
        );

        return response()->json([
            'license_key' => [
                'id' => $licenseKey->id,
                'key' => $licenseKey->key,
                'customer_email' => $licenseKey->customer_email,
            ],
            'licenses' => $licenseKey->licenses->map(fn($license) => [
                'product' => $license->product->code,
                'status' => $license->status,
                'expires_at' => $license->expires_at->toDateString(),
            ]),
        ], 201);
    }
}
