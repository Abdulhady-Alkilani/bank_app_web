<?php

namespace App\Services\Customer;

use App\Enums\AccountStatus;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use App\Notifications\CustomerSecurityNotification;
use Illuminate\Support\Facades\Route;

/**
 * Turns status changes of transactions / accounts into customer notifications (CUS-03).
 * Triggered by model events registered in CustomerPortalServiceProvider; can be muted for bulk simulation.
 */
class SecurityNotifier
{
    private static bool $muted = false;

    public static function mute(bool $muted = true): void
    {
        self::$muted = $muted;
    }

    public function transactionChanged(Transaction $transaction): void
    {
        if (self::$muted || ! $transaction->wasChanged('status')) {
            return;
        }

        $key = match ($transaction->status) {
            TransactionStatus::Held => 'held',
            TransactionStatus::Blocked => 'blocked',
            default => null,
        };

        if ($key === null) {
            return;
        }

        $account = $transaction->account;
        $this->send($account, __('customer.notifications.transaction_'.$key, [
            'amount' => number_format((float) $transaction->amount, 2),
            'account' => $account?->account_number,
        ]), $this->route('portal.history'), 'transaction_'.$key);
    }

    public function accountChanged(Account $account): void
    {
        if (self::$muted || ! $account->wasChanged('status')) {
            return;
        }

        match ($account->status) {
            AccountStatus::Frozen => $this->send($account, __('customer.notifications.account_frozen', ['account' => $account->account_number]), $this->route('portal.accounts'), 'account_frozen'),
            AccountStatus::Active => $this->send($account, __('customer.notifications.account_unfrozen', ['account' => $account->account_number]), $this->route('portal.accounts'), 'account_unfrozen'),
            default => null,
        };
    }

    private function send(?Account $account, string $message, ?string $url, string $type): void
    {
        $user = $account?->customer?->user;

        $user?->notify(new CustomerSecurityNotification($message, $url, $type));
    }

    private function route(string $name): ?string
    {
        return Route::has($name) ? route($name, [], false) : null;
    }
}
