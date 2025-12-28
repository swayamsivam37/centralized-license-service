<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseActivationController extends Controller
{
    public function __construct(
        protected LicenseActivationService $activationService
    ) {}

    /**
     * Activate a license key for a specific instance.
     *
     * This endpoint is intended for end-user products (untrusted clients).
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'instance_id' => ['required', 'string'],
        ]);

        $licenses = $this->activationService->activate(
            $validated['license_key'],
            $validated['instance_id']
        );

        return response()->json([
            'status' => 'active',
            'licenses' => $licenses,
        ]);
    }
}
