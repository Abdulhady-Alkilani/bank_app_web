<?php

namespace App\Livewire\Customer;

use App\Enums\CustomerReportStatus;
use App\Enums\LabelSource;
use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Models\CustomerReport;
use App\Models\Transaction;
use App\Services\Audit\AuditLogger;
use App\Services\Labels\LabelService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * CUS-05: report a transaction the customer did not make, and follow the status of the reports.
 */
class Reports extends Component
{
    use InteractsWithCustomer;

    public string $transactionId = '';

    public string $description = '';

    public bool $submitted = false;

    public function submit(LabelService $labels): void
    {
        $this->validate([
            'transactionId' => ['required', 'integer'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $transaction = Transaction::query()
            ->whereIn('account_id', $this->customer()->accounts()->select('id'))
            ->whereKey((int) $this->transactionId)
            ->first();

        if (! $transaction) {
            $this->addError('transactionId', __('customer.reports.invalid_transaction'));

            return;
        }

        $report = CustomerReport::create([
            'customer_id' => $this->customer()->id,
            'transaction_id' => $transaction->id,
            'description' => $this->description,
            'status' => CustomerReportStatus::New,
        ]);

        $labels->record($transaction, null, null, true, LabelSource::Customer, auth()->user());
        app(AuditLogger::class)->log('customer.report_created', $report, [], ['transaction_id' => $transaction->id]);

        $this->reset(['transactionId', 'description']);
        $this->submitted = true;
    }

    public function render(): View
    {
        $customer = $this->customer();
        $accountIds = $customer->accounts()->select('id');

        return view('livewire.customer.reports', [
            'transactions' => Transaction::query()->with('beneficiary')->whereIn('account_id', $accountIds)
                ->latest('transaction_time')->limit(30)->get(),
            'reports' => $customer->reports()->with('transaction')->latest()->limit(50)->get(),
        ])->title(__('customer.reports.title'));
    }
}
