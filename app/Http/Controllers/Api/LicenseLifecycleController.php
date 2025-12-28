<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\License;
use App\Services\LicenseLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseLifecycleController extends Controller
{
    public function __construct(
        protected LicenseLifecycleService $lifecycleService
    ) {}

    /**
     * Change the lifecycle state of a license.
     *
     * This endpoint is intended for trusted brand systems.
     */
    public function update(
        Request $request,
        Brand $brand,
        License $license
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:renew,suspend,resume,cancel'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $updatedLicense = $this->lifecycleService->change(
            $brand,
            $license,
            $validated['action'],
            $validated['expires_at'] ?? null
        );

        return response()->json([
            'id' => $updatedLicense->id,
            'status' => $updatedLicense->status,
            'expires_at' => optional($updatedLicense->expires_at)?->toDateString(),
        ]);
    }
}
