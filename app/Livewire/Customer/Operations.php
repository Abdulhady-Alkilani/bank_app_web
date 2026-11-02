<?php

namespace App\Livewire\Customer;

use App\Data\ScoringResult;
use App\Enums\Decision;
use App\Enums\EventType;
use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Models\Account;
use App\Services\Customer\PortalOperations;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * CUS-01: transfer / add beneficiary / change phone / raise limit. Every action goes through the scoring pipeline.
 */
class Operations extends Component
{
    use InteractsWithCustomer;

    #[Url(as: 'tab')]
    public string $tab = 'transfer';

    public ?int $accountId = null;

    public string $beneficiaryId = 'new';

    public string $beneficiaryAccount = '';

    public string $beneficiaryName = '';

    public string $bankName = '';

    public string $amount = '';

    public string $newPhone = '';

    public string $newLimit = '';

    /** @var array{status: string, message: string, score: int, decision: string}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->accountId = $this->accounts()->first()?->id;
        $this->tab = in_array($this->tab, ['transfer', 'beneficiary', 'phone', 'limit'], true) ? $this->tab : 'transfer';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->result = null;
        $this->resetValidation();
    }

    public function updatedAccountId(): void
    {
        $this->beneficiaryId = 'new';
        $this->result = null;
    }

    public function submitTransfer(PortalOperations $operations, SettingsService $settings): mixed
    {
        $account = $this->usableAccount();
        if (! $account) {
            return null;
        }

        $this->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $limit = (float) ($account->transfer_limit ?? $settings->get('transfer.default_limit', 20000));
        $amount = (float) $this->amount;
        if ($amount > (float) $account->balance) {
            throw ValidationException::withMessages(['amount' => __('customer.ops.insufficient_balance')]);
        }
        if ($amount > $limit) {
            throw ValidationException::withMessages(['amount' => __('customer.ops.over_limit', ['limit' => number_format($limit, 2)])]);
        }

        if ($this->beneficiaryId !== 'new') {
            $beneficiary = $account->beneficiaries()->whereKey((int) $this->beneficiaryId)->first();
            if (! $beneficiary || $beneficiary->is_frozen) {
                throw ValidationException::withMessages(['beneficiaryId' => __('customer.ops.beneficiary_unavailable')]);
            }
            $target = [
                'beneficiary_account' => $beneficiary->beneficiary_account,
                'beneficiary_name' => $beneficiary->beneficiary_name,
                'bank_name' => $beneficiary->bank_name,
            ];
        } else {
            $this->validate([
                'beneficiaryAccount' => ['required', 'string', 'max:64', 'different:'.$account->account_number],
                'beneficiaryName' => ['required', 'string', 'max:150'],
                'bankName' => ['nullable', 'string', 'max:150'],
            ]);
            $target = [
                'beneficiary_account' => $this->beneficiaryAccount,
                'beneficiary_name' => $this->beneficiaryName,
                'bank_name' => $this->bankName,
            ];
        }

        return $this->run($operations, $account, EventType::Transfer, ['amount' => $amount] + $target, function () {
            $this->reset(['amount', 'beneficiaryAccount', 'beneficiaryName', 'bankName']);
            $this->beneficiaryId = 'new';
        });
    }

    public function submitBeneficiary(PortalOperations $operations): mixed
    {
        $account = $this->usableAccount();
        if (! $account) {
            return null;
        }

        $this->validate([
            'beneficiaryAccount' => ['required', 'string', 'max:64', 'different:'.$account->account_number],
            'beneficiaryName' => ['required', 'string', 'max:150'],
            'bankName' => ['nullable', 'string', 'max:150'],
        ]);

        return $this->run($operations, $account, EventType::AddBeneficiary, [
            'beneficiary_account' => $this->beneficiaryAccount,
            'beneficiary_name' => $this->beneficiaryName,
            'bank_name' => $this->bankName,
        ], fn () => $this->reset(['beneficiaryAccount', 'beneficiaryName', 'bankName']));
    }

    public function submitPhone(PortalOperations $operations): mixed
    {
        $account = $this->usableAccount();
        if (! $account) {
            return null;
        }

        $this->validate(['newPhone' => ['required', 'string', 'regex:/^\+?[0-9\s-]{7,20}$/']]);

        return $this->run($operations, $account, EventType::ChangePhone, ['new_phone' => $this->newPhone], fn () => $this->reset('newPhone'));
    }

    public function submitLimit(PortalOperations $operations): mixed
    {
        $account = $this->usableAccount();
        if (! $account) {
            return null;
        }

        $this->validate(['newLimit' => ['required', 'numeric', 'min:0', 'max:1000000000']]);

        return $this->run($operations, $account, EventType::RaiseLimit, ['new_limit' => (float) $this->newLimit], fn () => $this->reset('newLimit'));
    }

    /**
     * The selected account, or null (with the frozen notice) when it can no longer be used.
     */
    private function usableAccount(): ?Account
    {
        $account = $this->ownedAccount($this->accountId);

        if ($account->isFrozen()) {
            $this->result = [
                'status' => 'frozen',
                'message' => __('customer.ops.account_frozen'),
                'score' => 100,
                'decision' => Decision::Block->value,
            ];

            return null;
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function run(PortalOperations $operations, Account $account, EventType $type, array $data, callable $onDone): mixed
    {
        $outcome = $operations->submit($this->customer(), $account, $type, $data);
        $onDone();

        return $this->present($outcome, $type);
    }

    private function present(ScoringResult $outcome, EventType $type): mixed
    {
        $this->result = [
            'status' => $outcome->decision->value,
            'message' => match ($outcome->decision) {
                Decision::Allow => __('customer.ops.allowed.'.$type->value),
                Decision::Challenge => __('customer.ops.challenged'),
                Decision::Block => __('customer.ops.blocked'),
            },
            'score' => $outcome->score,
            'decision' => $outcome->decision->value,
        ];

        if ($outcome->decision === Decision::Challenge && $outcome->otpChallengeId) {
            return $this->redirectRoute('portal.otp', ['challenge' => $outcome->otpChallengeId], navigate: true);
        }

        return null;
    }

    public function render(): View
    {
        $accounts = $this->accounts();
        $account = $accounts->firstWhere('id', $this->accountId) ?? $accounts->first();

        return view('livewire.customer.operations', [
            'accounts' => $accounts,
            'account' => $account,
            'beneficiaries' => $account ? $account->beneficiaries()->where('is_frozen', false)->orderBy('beneficiary_name')->get() : collect(),
        ])->title(__('customer.ops.title'));
    }
}
