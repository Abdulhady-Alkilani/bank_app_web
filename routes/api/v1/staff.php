<?php

use App\Http\Controllers\Api\V1\StaffAlertController;
use Illuminate\Support\Facades\Route;

// Staff API (/api/v1) — already wrapped in auth:sanctum.
// Alerts & network: Agent C. GET /models/compare is owned by Agent D.

Route::middleware('permission:alerts.view')->group(function () {
    Route::get('/alerts', [StaffAlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/{alert}', [StaffAlertController::class, 'show'])->name('alerts.show');
    Route::post('/alerts/{alert}/decision', [StaffAlertController::class, 'decide'])->middleware('permission:alerts.decide')->name('alerts.decision');
    Route::get('/accounts/{account}/network', [StaffAlertController::class, 'network'])->name('accounts.network');
});
