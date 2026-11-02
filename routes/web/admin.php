<?php

use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Backups;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Integrations;
use App\Livewire\Admin\ModelRegistry;
use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Rules;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\SystemHealth;
use App\Livewire\Admin\Users;
use App\Livewire\Admin\Watchlists;
use Illuminate\Support\Facades\Route;

// Group: prefix "admin", name "admin.", middleware auth + role:admin + staff.2fa (see routes/web.php).
// Owner: Agent D.

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/users', Users::class)->middleware('permission:users.manage')->name('users');
Route::get('/roles', Roles::class)->middleware('permission:roles.manage')->name('roles');
Route::get('/settings', Settings::class)->middleware('permission:settings.manage')->name('settings');
Route::get('/integrations', Integrations::class)->middleware('permission:integrations.manage')->name('integrations');
Route::get('/rules', Rules::class)->middleware('permission:rules.manage')->name('rules');
Route::get('/watchlists', Watchlists::class)->middleware('permission:lists.manage')->name('watchlists');
Route::get('/models', ModelRegistry::class)->middleware('permission:models.view')->name('models');
Route::get('/health', SystemHealth::class)->middleware('permission:system.health')->name('health');
Route::get('/audit', AuditLog::class)->middleware('permission:audit.view')->name('audit');
Route::get('/backups', Backups::class)->middleware('permission:backups.manage')->name('backups');
