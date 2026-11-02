<?php

namespace App\Services\Simulation;

use App\Enums\AccountStatus;
use App\Models\Account;
use App\Models\AccountAction;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InvestigationCase;
use App\Models\Label;
use App\Models\OtpChallenge;
use App\Models\Transaction;
use App\Notifications\CustomerSecurityNotification;
use App\Services\Customer\SecurityNotifier;

/**
 * Removes the data produced by a simulation scenario. Everything is identified by the idempotency-key prefix
 * `sim-{scenario}-`, the `*.{scenario}@sim.bank.test` customers and the `sim-{scenario}-*` device fingerprints.
 */
class SimulationReset
{
    public const AHMED_ACCOUNT = '1000000001';

    public const AHMED_PHONE = '+967711000001';

    public function reset(string $scenario): void
    {
        $prefix = "sim-{$scenario}-";

        Transaction::query()->where('idempotency_key', 'like', $prefix.'%')->delete();
        AccountEvent::query()->where('idempotency_key', 'like', $prefix.'%')->delete();

        Customer::query()->where('email', 'like', "%.{$scenario}@sim.bank.test")->get()->each->delete();
        Device::query()->where('device_fingerprint', 'like', $prefix.'%')->delete();

        if ($scenario === 'ahmed') {
            $this->restoreAhmed();
        }

        InvestigationCase::query()->doesntHave('alerts')->delete();
        Label::query()->whereNull('transaction_id')->whereNull('event_id')->whereNull('alert_id')->delete();
    }

    private function restoreAhmed(): void
    {
        $account = Account::query()->where('account_number', self::AHMED_ACCOUNT)->first();
        if (! $account) {
            return;
        }

        $customer = $account->customer;

        AccountAction::query()->where('account_id', $account->id)->delete();
        Beneficiary::query()->where('account_id', $account->id)->where('beneficiary_account', 'like', '2000000%')->delete();
        OtpChallenge::query()->where('customer_id', $customer->id)->delete();
        $customer->user?->notifications()->where('type', CustomerSecurityNotification::class)->delete();

        SecurityNotifier::mute();
        try {
            $account->forceFill(['status' => AccountStatus::Active, 'balance' => 25000, 'transfer_limit' => null])->save();
        } finally {
            SecurityNotifier::mute(false);
        }
        $customer->forceFill(['phone' => self::AHMED_PHONE])->save();
    }
}
