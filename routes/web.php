<?php

use App\Http\Controllers\HomeRedirect;
use App\Http\Controllers\LocaleController;
use App\Livewire\Profile\Security;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect('/dashboard') : redirect()->route('login'))->name('home');

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', HomeRedirect::class)->name('dashboard');
    Route::get('/profile/security', Security::class)->middleware('password.confirm')->name('profile.security');
});

/*
|--------------------------------------------------------------------------
| Role areas — each agent adds routes inside its own file.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin', 'staff.2fa'])->prefix('admin')->name('admin.')
    ->group(base_path('routes/web/admin.php'));

Route::middleware(['auth', 'role:supervisor,admin', 'staff.2fa'])->prefix('supervisor')->name('supervisor.')
    ->group(base_path('routes/web/supervisor.php'));

Route::middleware(['auth', 'role:analyst,supervisor,admin', 'staff.2fa'])->prefix('analyst')->name('analyst.')
    ->group(base_path('routes/web/analyst.php'));

Route::middleware(['auth', 'role:customer'])->prefix('portal')->name('portal.')
    ->group(base_path('routes/web/customer.php'));

// API documentation (OpenAPI 3.1 rendered with Swagger UI) — any authenticated user.
Route::get('/docs/api', fn () => view('docs.api'))->middleware('auth')->name('docs.api');
