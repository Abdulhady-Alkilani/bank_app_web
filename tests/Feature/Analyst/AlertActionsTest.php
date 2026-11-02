<?php

use App\Enums\AccountActionStatus;
use App\Enums\AccountActionType;
use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\TransactionStatus;
use App\Livewire\Analyst\AlertShow;
use App\Models\AccountAction;
use App\Models\Approval;
use App\Models\Beneficiary;
use App\Models\User;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

it('creates a pending four-eyes approval when an analyst requests an account freeze', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $account = $alert->riskScore->transaction->account;

    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])
        ->set('actionReason', 'Suspected takeover')
        ->call('performAction', 'freeze_account')
        ->assertHasNoErrors()
        ->assertSee(__('analyst.alert.awaiting_approval'));

    $action = AccountAction::first();
    expect($action->action)->toBe(AccountActionType::FreezeAccount)
        ->and($action->status)->toBe(AccountActionStatus::PendingApproval)
        ->and($action->alert_id)->toBe($alert->id)
        ->and($action->approval->status)->toBe(ApprovalStatus::Pending)
        ->and($action->approval->type)->toBe(ApprovalType::FreezeAccount)
        ->and($account->fresh()->status)->toBe(AccountStatus::Active);

    // A second identical request is rejected while one is pending.
    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])
        ->set('actionReason', 'again')
        ->call('performAction', 'freeze_account')
        ->assertHasErrors('actionReason');

    expect(Approval::count())->toBe(1);
});

it('requests a beneficiary freeze with approval', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $tx = $alert->riskScore->transaction;
    $beneficiary = Beneficiary::factory()->create(['account_id' => $tx->account_id]);
    $tx->update(['beneficiary_id' => $beneficiary->id]);

    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])
        ->set('actionReason', 'Mule account')
        ->call('performAction', 'freeze_beneficiary');

    expect(AccountAction::first()->beneficiary_id)->toBe($beneficiary->id)
        ->and($beneficiary->fresh()->is_frozen)->toBeFalse();
});

it('blocks a transaction and records an OTP requirement immediately', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();

    $component = Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert]);
    $component->set('actionReason', 'Block it')->call('performAction', 'block_transaction');
    $component->set('actionReason', 'Step-up auth')->call('performAction', 'require_otp');

    expect($alert->riskScore->transaction->fresh()->status)->toBe(TransactionStatus::Blocked)
        ->and(AccountAction::where('action', AccountActionType::RequireOtp)->first()->status)->toBe(AccountActionStatus::Executed)
        ->and(AccountAction::count())->toBe(2);
});

it('denies unfreeze requests to analysts but allows supervisors', function () {
    $alert = makeAnalystAlert();
    $alert->riskScore->transaction->account->update(['status' => AccountStatus::Frozen]);

    Livewire::actingAs(User::factory()->analyst()->create())->test(AlertShow::class, ['alert' => $alert])
        ->set('actionReason', 'Verified legit')
        ->call('performAction', 'unfreeze_account')
        ->assertHasErrors('actionReason');
    expect(AccountAction::count())->toBe(0);

    Livewire::actingAs(User::factory()->supervisor()->create())->test(AlertShow::class, ['alert' => $alert])
        ->set('actionReason', 'Verified legit')
        ->call('performAction', 'unfreeze_account')
        ->assertHasNoErrors();
    expect(AccountAction::first()->status)->toBe(AccountActionStatus::PendingApproval);
});
