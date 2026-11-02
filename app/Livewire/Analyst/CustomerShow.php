<?php

namespace App\Livewire\Analyst;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Audit\AuditLogger;
use App\Services\Investigation\CustomerInsights;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class CustomerShow extends Component
{
    public Customer $customer;

    /** Optional alert id: the flagged item is compared with the customer's usual behaviour. */
    #[Url(except: null)]
    public ?int $alert = null;

    public bool $revealed = false;

    public function mount(Customer $customer): void
    {
        Gate::authorize('viewAny', Alert::class);

        $this->customer = $customer;
    }

    /**
     * PII is masked by default; revealing it is an audited action.
     */
    public function reveal(AuditLogger $audit): void
    {
        Gate::authorize('viewAny', Alert::class);

        $this->revealed = true;
        $audit->log('customer.pii_revealed', $this->customer, [], ['alert_id' => $this->alert]);
    }

    public function hide(): void
    {
        $this->revealed = false;
    }

    public function render(CustomerInsights $insights): View
    {
        $this->customer->load(['accounts.beneficiaries', 'customerDevices.device']);

        $accountIds = $this->customer->accounts->pluck('id');
        $currentAlert = $this->alert
            ? Alert::query()->with(['riskScore.transaction.device'])->find($this->alert)
            : null;
        if ($currentAlert && ! auth()->user()->can('view', $currentAlert)) {
            $currentAlert = null;
        }

        return view('livewire.analyst.customer-show', [
            'patterns' => $insights->patterns($this->customer),
            'comparison' => $currentAlert ? $insights->compare($this->customer, $currentAlert) : [],
            'transactions' => Transaction::query()->with(['account', 'riskScore'])->whereIn('account_id', $accountIds)->latest('transaction_time')->limit(15)->get(),
            'alerts' => Alert::query()
                ->whereHas('riskScore', fn ($q) => $q->whereHas('transaction', fn ($t) => $t->whereIn('account_id', $accountIds))
                    ->orWhereHas('event', fn ($e) => $e->whereIn('account_id', $accountIds)))
                ->with('riskScore')
                ->latest()
                ->limit(10)
                ->get()
                ->filter(fn (Alert $a) => auth()->user()->can('view', $a)),
        ])->title(__('analyst.customer.title'));
    }
}
