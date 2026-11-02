<?php

namespace App\Livewire\Customer;

use App\Enums\EventType;
use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Models\AccountEvent;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * CUS-07: recent sign-ins and transactions with their statuses.
 */
class Activity extends Component
{
    use InteractsWithCustomer;

    public function render(): View
    {
        $logins = AccountEvent::query()
            ->with(['device', 'account'])
            ->whereIn('account_id', $this->customer()->accounts()->select('id'))
            ->where('event_type', EventType::Login->value)
            ->latest('event_time')
            ->limit(15)
            ->get();

        return view('livewire.customer.activity', ['logins' => $logins])->title(__('customer.activity.title'));
    }
}
