<?php

use App\Http\Controllers\Api\ModelComparisonController;
use Illuminate\Support\Facades\Route;

// GET /api/v1/models/compare — admin & supervisor (models.view).
Route::middleware(['auth:sanctum', 'permission:models.view'])
    ->get('/models/compare', ModelComparisonController::class)->name('models.compare');
