<?php

namespace App\Livewire\Customer;

use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Accounts extends Component
{
    use InteractsWithCustomer;

    public function render(SettingsService $settings): View
    {
        $accounts = $this->customer()->accounts()->with('beneficiaries')->orderBy('id')->get();

        return view('livewire.customer.accounts', [
            'accounts' => $accounts,
            'frozen' => $accounts->filter->isFrozen(),
            'defaultLimit' => (float) $settings->get('transfer.default_limit', 20000),
        ])->title(__('customer.accounts.title'));
    }
}
