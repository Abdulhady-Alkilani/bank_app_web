<?php

namespace App\Providers;

use App\Listeners\Customer\RecordPortalLogin;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\Customer\SecurityNotifier;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CustomerPortalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SecurityNotifier::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordPortalLogin::class);

        Transaction::updated(fn (Transaction $transaction) => app(SecurityNotifier::class)->transactionChanged($transaction));
        Account::updated(fn (Account $account) => app(SecurityNotifier::class)->accountChanged($account));
    }
}
