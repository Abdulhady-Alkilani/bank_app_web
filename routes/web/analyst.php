<?php

use App\Http\Controllers\Analyst\CaseAttachmentController;
use App\Http\Controllers\Analyst\CaseReportController;
use App\Livewire\Analyst\AccountNetwork;
use App\Livewire\Analyst\AlertQueue;
use App\Livewire\Analyst\AlertShow;
use App\Livewire\Analyst\Assistant;
use App\Livewire\Analyst\CaseIndex;
use App\Livewire\Analyst\CaseShow;
use App\Livewire\Analyst\CustomerShow;
use App\Livewire\Analyst\Dashboard;
use App\Livewire\Analyst\RuleSuggestions;
use App\Livewire\Analyst\Search;
use Illuminate\Support\Facades\Route;

// Group: prefix "analyst", name "analyst.", middleware auth + role:analyst,supervisor,admin + staff.2fa.
// Owner: Agent C.

Route::get('/', Dashboard::class)->name('dashboard');

Route::middleware('permission:alerts.view')->group(function () {
    Route::get('/alerts', AlertQueue::class)->name('alerts');
    Route::get('/alerts/{alert}', AlertShow::class)->name('alerts.show');
    Route::get('/customers/{customer}', CustomerShow::class)->name('customers.show');
    Route::get('/accounts/{account}/network', AccountNetwork::class)->name('accounts.network');
    Route::get('/search', Search::class)->name('search');
});

Route::middleware('permission:cases.manage')->group(function () {
    Route::get('/cases', CaseIndex::class)->name('cases');
    Route::get('/cases/{case}', CaseShow::class)->name('cases.show');
    Route::get('/cases/{case}/pdf', [CaseReportController::class, 'pdf'])->name('cases.pdf');
    Route::get('/case-attachments/{attachment}', [CaseAttachmentController::class, 'download'])->name('cases.attachments.download');
});

Route::get('/assistant', Assistant::class)->middleware('permission:mcp.use,alerts.view')->name('assistant');
Route::get('/rule-suggestions', RuleSuggestions::class)->middleware('permission:rule_suggestions.create')->name('rule-suggestions');
