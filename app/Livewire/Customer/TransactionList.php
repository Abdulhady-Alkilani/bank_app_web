<?php

namespace App\Livewire\Customer;

use App\Enums\CustomerReportStatus;
use App\Enums\LabelSource;
use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Models\CustomerReport;
use App\Models\Label;
use App\Models\Transaction;
use App\Services\Audit\AuditLogger;
use App\Services\Labels\LabelService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Recent transactions of the customer with the CUS-04 one-click "yes, it was me / no, it wasn't me" feedback.
 */
class TransactionList extends Component
{
    use InteractsWithCustomer;

    public int $limit = 10;

    public function confirm(int $transactionId, LabelService $labels): void
    {
        $transaction = $this->ownedTransaction($transactionId);

        if ($this->answered($transaction)) {
            return;
        }

        $labels->record($transaction, null, null, false, LabelSource::Customer, auth()->user());
        app(AuditLogger::class)->log('customer.transaction_confirmed', $transaction, [], ['is_fraud' => false]);
    }

    public function deny(int $transactionId, LabelService $labels): void
    {
        $transaction = $this->ownedTransaction($transactionId);

        if ($this->answered($transaction)) {
            return;
        }

        $labels->record($transaction, null, null, true, LabelSource::Customer, auth()->user());

        CustomerReport::firstOrCreate(
            ['customer_id' => $this->customer()->id, 'transaction_id' => $transaction->id],
            ['description' => __('customer.feedback.report_text'), 'status' => CustomerReportStatus::New],
        );

        app(AuditLogger::class)->log('customer.transaction_denied', $transaction, [], ['is_fraud' => true]);
    }

    private function ownedTransaction(int $id): Transaction
    {
        return Transaction::query()
            ->whereIn('account_id', $this->customer()->accounts()->select('id'))
            ->whereKey($id)
            ->firstOrFail();
    }

    private function answered(Transaction $transaction): bool
    {
        return Label::query()->where('transaction_id', $transaction->id)->where('source', LabelSource::Customer->value)->exists();
    }

    public function render(): View
    {
        $transactions = Transaction::query()
            ->with(['account', 'beneficiary'])
            ->whereIn('account_id', $this->customer()->accounts()->select('id'))
            ->latest('transaction_time')
            ->latest('id')
            ->limit($this->limit)
            ->get();

        $answers = Label::query()
            ->whereIn('transaction_id', $transactions->pluck('id'))
            ->where('source', LabelSource::Customer->value)
            ->pluck('is_fraud', 'transaction_id');

        return view('livewire.customer.transaction-list', [
            'transactions' => $transactions,
            'answers' => $answers,
        ]);
    }
}
