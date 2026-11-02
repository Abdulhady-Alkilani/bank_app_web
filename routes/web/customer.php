<?php

use App\Livewire\Customer\Accounts;
use App\Livewire\Customer\Activity;
use App\Livewire\Customer\Dashboard;
use App\Livewire\Customer\Operations;
use App\Livewire\Customer\OtpVerify;
use App\Livewire\Customer\Reports;
use App\Livewire\Customer\TrustedDevices;
use Illuminate\Support\Facades\Route;

// Group: prefix "portal", name "portal.", middleware auth + role:customer.
// Owner: Agent E.

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/accounts', Accounts::class)->name('accounts');
Route::get('/transfer', Operations::class)->name('transfer');
Route::get('/otp/{challenge}', OtpVerify::class)->name('otp');
Route::get('/history', Activity::class)->name('history');
Route::get('/reports', Reports::class)->name('reports');
Route::get('/devices', TrustedDevices::class)->name('devices');
