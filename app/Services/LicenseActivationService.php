<?php

namespace App\Services;

use App\Models\Activation;
use App\Models\LicenseKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service responsible for activating license keys for end-user instances.
 *
 * This service is used by untrusted end-user products (e.g. plugins, apps)
 * to activate a license key for a specific instance identifier.
 *
 * It returns the set of valid licenses (entitlements) unlocked by the key.
 */
class LicenseActivationService
{
    /**
     * Activate a license key for a specific instance.
     *
     * @param  string  $licenseKeyValue
     *                                   The license key string provided by the end user.
     * @param  string  $instanceId
     *                              A unique identifier for the instance (e.g. site URL, machine ID).
     * @return array
     *               A list of active licenses unlocked by this key.
     *
     * @throws InvalidArgumentException
     *                                  If the license key is invalid, has no valid licenses,
     *                                  or is already activated for the given instance.
     */
    public function activate(string $licenseKeyValue, string $instanceId): array
    {
        return DB::transaction(function () use ($licenseKeyValue, $instanceId) {

            $licenseKey = LicenseKey::where('key', $licenseKeyValue)
                ->with(['licenses'])
                ->first();

            if (! $licenseKey) {
                throw new InvalidArgumentException('Invalid license key.');
            }

            $validLicenses = $licenseKey->licenses
                ->filter(fn ($license) => $this->isLicenseUsable($license))
                ->values();

            if ($validLicenses->isEmpty()) {
                throw new InvalidArgumentException(
                    'No valid licenses associated with this license key.'
                );
            }

            $existingActivation = Activation::where('license_key_id', $licenseKey->id)
                ->where('instance_id', $instanceId)
                ->whereNull('deactivated_at')
                ->first();

            if ($existingActivation) {
                // Idempotent behavior: already activated
                return $this->formatLicenses($validLicenses);
            }

            Activation::create([
                'license_key_id' => $licenseKey->id,
                'instance_id' => $instanceId,
                'activated_at' => Carbon::now(),
            ]);

            return $this->formatLicenses($validLicenses);
        });
    }

    /**
     * Determine whether a license can be used for activation.
     */
    protected function isLicenseUsable($license): bool
    {
        if ($license->status !== 'valid') {
            return false;
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Format licenses for API response.
     */
    protected function formatLicenses($licenses): array
    {
        return $licenses->map(fn ($license) => [
            'product_code' => $license->product->code,
            'status' => $license->status,
            'expires_at' => optional($license->expires_at)?->toDateString(),
        ])->toArray();
    }
}
