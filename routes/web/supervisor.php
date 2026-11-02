<?php

use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\ModelRegistry;
use App\Livewire\Supervisor\Approvals;
use App\Livewire\Supervisor\Assignments;
use App\Livewire\Supervisor\Dashboard;
use App\Livewire\Supervisor\QaReview;
use App\Livewire\Supervisor\Reports;
use App\Livewire\Supervisor\RuleSuggestions;
use Illuminate\Support\Facades\Route;

// Group: prefix "supervisor", name "supervisor.", middleware auth + role:supervisor,admin + staff.2fa.
// Owner: Agent D.

Route::get('/', Dashboard::class)->middleware('permission:reports.view')->name('dashboard');
Route::get('/assignments', Assignments::class)->middleware('permission:assignments.manage')->name('assignments');
Route::get('/approvals', Approvals::class)->middleware('permission:approvals.approve')->name('approvals');
Route::get('/qa', QaReview::class)->middleware('permission:qa.review')->name('qa');
Route::get('/rule-suggestions', RuleSuggestions::class)->middleware('permission:rule_suggestions.review')->name('rule-suggestions');
Route::get('/reports', Reports::class)->middleware('permission:reports.view')->name('reports');
Route::get('/audit', AuditLog::class)->middleware('permission:audit.view')->name('audit');
Route::get('/models', ModelRegistry::class)->middleware('permission:models.view')->name('models');
