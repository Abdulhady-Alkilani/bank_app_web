<?php

use Illuminate\Support\Facades\Route;

// All routes below are served under /api/v1.
Route::prefix('v1')->name('api.v1.')->group(function () {
    // External bank/simulator API — HMAC + idempotency + rate-limit middleware (Agent B).
    Route::group([], base_path('routes/api/v1/external.php'));

    // Staff API — auth:sanctum.
    Route::middleware('auth:sanctum')->group(base_path('routes/api/v1/staff.php'));

    // Model registry comparison (Agent D) — auth:sanctum + permission:models.view.
    Route::group([], base_path('routes/api/v1/models.php'));

    // ML service API — service token (Agent B / D).
    Route::group([], base_path('routes/api/v1/ml.php'));
});
