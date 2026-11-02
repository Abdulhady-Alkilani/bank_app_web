<?php

use App\Enums\AccountActionStatus;
use App\Enums\AccountActionType;
use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\TransactionStatus;
use App\Exceptions\ApprovalException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccountActionService;
use App\Services\Approvals\ApprovalService;

it('freezes and unfreezes an account immediately and audits it', function () {
    $account = Account::factory()->create();
    $service = app(AccountActionService::class);

    $action = $service->execute(AccountActionType::FreezeAccount, $account, ['reason' => 'auto-block']);
    expect($account->fresh()->status)->toBe(AccountStatus::Frozen)
        ->and($action->status)->toBe(AccountActionStatus::Executed)
        ->and($action->executed_at)->not->toBeNull();

    $service->execute(AccountActionType::UnfreezeAccount, $account);
    expect($account->fresh()->status)->toBe(AccountStatus::Active)
        ->and(AuditLog::where('action', 'account_action.freeze_account')->count())->toBe(1);
});

it('freezes beneficiaries and blocks transactions', function () {
    $account = Account::factory()->create();
    $beneficiary = Beneficiary::factory()->create(['account_id' => $account->id]);
    $transaction = Transaction::factory()->create(['account_id' => $account->id]);
    $service = app(AccountActionService::class);

    $service->execute(AccountActionType::FreezeBeneficiary, $account, ['beneficiary' => $beneficiary]);
    $service->execute(AccountActionType::BlockTransaction, $account, ['transaction' => $transaction]);

    expect($beneficiary->fresh()->is_frozen)->toBeTrue()
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Blocked);
});

it('requires approval for a requested freeze and executes it on approval', function () {
    $analyst = User::factory()->analyst()->create();
    $supervisor = User::factory()->supervisor()->create();
    $account = Account::factory()->create();

    $action = app(AccountActionService::class)->requestWithApproval(AccountActionType::FreezeAccount, $account, $analyst, 'suspicious');

    expect($action->status)->toBe(AccountActionStatus::PendingApproval)
        ->and($action->approval->status)->toBe(ApprovalStatus::Pending)
        ->and($action->approval->type)->toBe(ApprovalType::FreezeAccount)
        ->and($account->fresh()->status)->toBe(AccountStatus::Active);

    $approval = app(ApprovalService::class)->approve($action->approval, $supervisor);

    expect($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->approved_by)->toBe($supervisor->id)
        ->and($action->fresh()->status)->toBe(AccountActionStatus::Executed)
        ->and($account->fresh()->status)->toBe(AccountStatus::Frozen);
});

it('enforces four-eyes: the requester cannot approve their own request', function () {
    $supervisor = User::factory()->supervisor()->create();
    $account = Account::factory()->create();

    $action = app(AccountActionService::class)->requestWithApproval(AccountActionType::FreezeAccount, $account, $supervisor, 'self');

    expect(fn () => app(ApprovalService::class)->approve($action->approval, $supervisor))
        ->toThrow(ApprovalException::class);

    expect($account->fresh()->status)->toBe(AccountStatus::Active);
});

it('refuses approvals from users without the permission', function () {
    $analyst = User::factory()->analyst()->create();
    $other = User::factory()->analyst()->create();
    $approval = app(ApprovalService::class)->request(ApprovalType::FreezeAccount, Account::factory()->create(), $analyst, 'x');

    expect(fn () => app(ApprovalService::class)->approve($approval, $other))->toThrow(ApprovalException::class);
});

it('rejects a request and its pending actions', function () {
    $analyst = User::factory()->analyst()->create();
    $supervisor = User::factory()->supervisor()->create();
    $account = Account::factory()->create();

    $action = app(AccountActionService::class)->requestWithApproval(AccountActionType::FreezeAccount, $account, $analyst, 'maybe');
    $approval = app(ApprovalService::class)->reject($action->approval, $supervisor, 'insufficient evidence');

    expect($approval->status)->toBe(ApprovalStatus::Rejected)
        ->and($action->fresh()->status)->toBe(AccountActionStatus::Rejected)
        ->and($account->fresh()->status)->toBe(AccountStatus::Active);

    expect(fn () => app(ApprovalService::class)->approve($approval, $supervisor))->toThrow(ApprovalException::class);
});
