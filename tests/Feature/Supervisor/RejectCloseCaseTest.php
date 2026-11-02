<?php

use App\Enums\ApprovalType;
use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\InvestigationCase;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

it('moves a case back to investigating when its close approval is rejected', function () {
    $analyst = User::factory()->analyst()->create();
    $supervisor = User::factory()->supervisor()->create();
    $case = InvestigationCase::factory()->create(['status' => CaseStatus::PendingApproval]);

    $service = app(ApprovalService::class);
    $approval = $service->request(ApprovalType::CloseHighValueCase, $case, $analyst, 'close');
    $service->reject($approval, $supervisor, 'not enough evidence');

    expect($case->fresh()->status)->toBe(CaseStatus::Investigating)
        ->and(AuditLog::where('action', 'case.reopened_after_rejection')->count())->toBe(1);
});
