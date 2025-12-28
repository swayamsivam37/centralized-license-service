<?php

use App\Http\Controllers\Api\BrandLicenseProvisioningController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/brands/{brand}/license-keys',
    [BrandLicenseProvisioningController::class, 'store']
);
