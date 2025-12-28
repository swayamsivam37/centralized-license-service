<?php

namespace App\Services;

use App\Models\LicenseKey;

class LicenseQueryService
{
    /**
     * List all licenses associated with a customer email
     * across the entire ecosystem.
     */
    public function listByCustomerEmail(string $email): array
    {
        $licenseKeys = LicenseKey::where('customer_email', $email)
            ->with([
                'licenses.product.brand',
            ])
            ->get();

        $results = [];

        foreach ($licenseKeys as $licenseKey) {
            foreach ($licenseKey->licenses as $license) {
                $results[] = [
                    'brand' => [
                        'id' => $license->product->brand->id,
                        'code' => $license->product->brand->code,
                        'name' => $license->product->brand->name,
                    ],
                    'product' => [
                        'code' => $license->product->code,
                        'name' => $license->product->name,
                    ],
                    'license_key' => $licenseKey->key,
                    'status' => $license->status,
                    'expires_at' => optional($license->expires_at)->toDateString(),
                ];
            }
        }

        return $results;
    }
}
