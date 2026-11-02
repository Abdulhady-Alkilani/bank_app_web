<?php

use App\Http\Controllers\Api\V1\External\EventController;
use App\Http\Controllers\Api\V1\External\OtpController;
use App\Http\Controllers\Api\V1\External\ScoreController;
use App\Http\Controllers\Api\V1\External\SyncController;
use App\Http\Controllers\Api\V1\External\VerificationResultController;
use Illuminate\Support\Facades\Route;

// External API (/api/v1) — authenticated by HMAC signature (api.hmac), rate limited per API client.
Route::middleware(['api.hmac', 'throttle:external-api'])->group(function () {
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::post('score', [ScoreController::class, 'store'])->name('score.store');
    Route::post('otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
    Route::post('verification-results', [VerificationResultController::class, 'store'])->name('verification-results.store');
    Route::post('sync', [SyncController::class, 'store'])->name('sync.store');
});
