<?php

namespace App\Livewire\Customer\Concerns;

use App\Models\Account;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Every portal component only ever works with the signed-in customer's own data.
 */
trait InteractsWithCustomer
{
    protected function customer(): Customer
    {
        $customer = auth()->user()?->customer;

        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }

    /**
     * @return Collection<int, Account>
     */
    protected function accounts(): Collection
    {
        return $this->customer()->accounts()->orderBy('id')->get();
    }

    protected function ownedAccount(int|string|null $id): Account
    {
        return $this->customer()->accounts()->whereKey((int) $id)->firstOrFail();
    }
}
