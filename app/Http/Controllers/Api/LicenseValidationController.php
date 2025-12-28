<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseValidationService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

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

        try {
            return response()->json(
                $this->validationService->validate(
                    $validated['license_key'],
                    $validated['instance_id'] ?? null
                )
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
