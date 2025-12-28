<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\LicenseQueryService;
use Illuminate\Http\Request;

class LicenseQueryController extends Controller
{
    public function __construct(
        protected LicenseQueryService $licenseQueryService
    ) {}

    /**
     * List all licenses associated with a customer email
     * across the entire ecosystem.
     *
     * This endpoint is intended for trusted brand systems only.
     */
    public function index(Brand $brand, Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $licenses = $this->licenseQueryService
            ->listByCustomerEmail($validated['email']);

        return response()->json([
            'customer_email' => $validated['email'],
            'licenses' => $licenses,
        ]);
    }
}
