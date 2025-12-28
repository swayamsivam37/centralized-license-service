<?php

use App\Http\Controllers\Api\BrandLicenseProvisioningController;
use App\Http\Controllers\Api\LicenseLifecycleController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/brands/{brand}/license-keys',
    [BrandLicenseProvisioningController::class, 'store']
);

Route::patch(
    '/brands/{brand}/licenses/{license}',
    [LicenseLifecycleController::class, 'update']
);