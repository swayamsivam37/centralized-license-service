<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseValidationService;
use Illuminate\Http\Request;

class LicenseValidationController extends Controller
{
    public function __construct(
        protected LicenseValidationService $validationService
    ) {}

    public function validateKey(Request $request)
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string'],
            'instance_id' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->validationService->validate(
                $validated['license_key'],
                $validated['instance_id'] ?? null
            )
        );
    }
}
