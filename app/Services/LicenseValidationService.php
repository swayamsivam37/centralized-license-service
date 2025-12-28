<?php

namespace App\Services;

use App\Models\LicenseKey;
use InvalidArgumentException;

class LicenseValidationService
{
    public function validate(string $licenseKeyValue, ?string $instanceId = null): array
    {
        $licenseKey = LicenseKey::where('key', $licenseKeyValue)
            ->with(['licenses.product', 'activations'])
            ->first();

        if (! $licenseKey) {
            throw new InvalidArgumentException('Invalid license key.');
        }

        $validLicenses = $licenseKey->licenses
            ->filter(
                fn ($license) => $license->status === 'valid' &&
                    (! $license->expires_at || $license->expires_at->isFuture())
            )
            ->values();

        if ($validLicenses->isEmpty()) {
            return [
                'status' => 'invalid',
                'licenses' => [],
                'seats' => [
                    'used' => $licenseKey->activations()->count(),
                    'remaining' => null,
                ],
            ];
        }

        return [
            'status' => 'valid',
            'licenses' => $validLicenses->map(fn ($license) => [
                'product_code' => $license->product->code,
                'status' => $license->status,
                'expires_at' => optional($license->expires_at)?->toDateString(),
            ])->toArray(),
            'seats' => [
                'used' => $licenseKey->activations()->count(),
                'remaining' => null, // seat limits not enforced
            ],
        ];
    }
}
