<?php

namespace App\Livewire\Customer;

use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use InteractsWithCustomer;

    public function render(): View
    {
        $customer = $this->customer();
        $accounts = $this->accounts();

        return view('livewire.customer.dashboard', [
            'customer' => $customer,
            'accounts' => $accounts,
            'frozen' => $accounts->filter->isFrozen(),
            'notifications' => auth()->user()->notifications()->latest()->limit(5)->get(),
        ])->title(__('customer.dashboard.title'));
    }
}
