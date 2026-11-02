<?php

use App\Http\Controllers\Api\V1\External\MlModelRunController;
use Illuminate\Support\Facades\Route;

// ML service API (/api/v1) — Bearer service token (fraud.ml_ingest_token).
Route::middleware('ml.token')->group(function () {
    Route::post('ml/model-runs', [MlModelRunController::class, 'store'])->name('ml.model-runs.store');
});
