<?php

namespace App\Livewire\Customer;

use App\Enums\OtpStatus;
use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Models\OtpChallenge;
use App\Services\Customer\PortalOperations;
use App\Services\Otp\OtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * CUS-02: enter the one-time code that releases a held operation.
 */
class OtpVerify extends Component
{
    use InteractsWithCustomer;

    public OtpChallenge $challenge;

    public string $code = '';

    /** @var 'pending'|'verified'|'failed'|'expired' */
    public string $outcome = 'pending';

    public ?string $error = null;

    public function mount(OtpChallenge $challenge): void
    {
        abort_unless($challenge->customer_id === $this->customer()->id, 404);

        $this->challenge = $challenge;
        $this->syncOutcome();
    }

    public function verify(OtpService $otp, PortalOperations $operations): void
    {
        $this->error = null;
        $this->validate(['code' => ['required', 'digits:6']]);

        $this->challenge->refresh();
        if ($this->challenge->status !== OtpStatus::Pending) {
            $this->syncOutcome();

            return;
        }

        if ($otp->verify($this->challenge, $this->code)) {
            $tx = $this->challenge->refresh()->transaction;
            if ($tx && $tx->status->value === 'completed') {
                $operations->debit($tx);
            }
            $this->outcome = 'verified';
            $this->code = '';

            return;
        }

        $this->challenge->refresh();
        $this->syncOutcome();
        if ($this->outcome === 'pending') {
            $this->error = __('customer.otp.wrong_code', ['left' => $otp->remainingAttempts($this->challenge)]);
        }
        $this->code = '';
    }

    private function syncOutcome(): void
    {
        $this->outcome = match (true) {
            $this->challenge->status === OtpStatus::Verified => 'verified',
            $this->challenge->status === OtpStatus::Failed => 'failed',
            $this->challenge->status === OtpStatus::Expired,
            $this->challenge->status === OtpStatus::Pending && $this->challenge->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public function render(OtpService $otp): View
    {
        $challenge = $this->challenge->loadMissing('transaction.beneficiary', 'event');

        return view('livewire.customer.otp-verify', [
            'challenge' => $challenge,
            'attemptsLeft' => $otp->remainingAttempts($challenge),
            'devCode' => app()->isLocal() ? Cache::get('otp:dev:'.$challenge->id) : null,
            'secondsLeft' => max(0, (int) now()->diffInSeconds($challenge->expires_at, false)),
        ])->title(__('customer.otp.title'));
    }
}
