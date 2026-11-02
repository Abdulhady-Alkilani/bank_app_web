<?php

namespace App\Services\Otp;

use App\Enums\OtpStatus;
use App\Enums\TransactionStatus;
use App\Mail\OtpCodeMail;
use App\Models\AccountEvent;
use App\Models\Customer;
use App\Models\OtpChallenge;
use App\Models\Transaction;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OtpService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create a 6-digit challenge for the subject and e-mail the code to the customer.
     * In local env the plain code is also cached for 5 minutes under `otp:dev:{id}` for the portal dev banner.
     */
    public function issue(Customer $customer, Transaction|AccountEvent $subject): OtpChallenge
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = (int) $this->settings->get('otp.ttl_seconds', 300);

        $challenge = OtpChallenge::create([
            'customer_id' => $customer->id,
            'transaction_id' => $subject instanceof Transaction ? $subject->id : null,
            'event_id' => $subject instanceof AccountEvent ? $subject->id : null,
            'code_hash' => Hash::make($code),
            'status' => OtpStatus::Pending,
            'attempts' => 0,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        if (app()->isLocal()) {
            Cache::put('otp:dev:'.$challenge->id, $code, now()->addMinutes(5));
        }

        if (filled($customer->email)) {
            try {
                Mail::to($customer->email)->send(new OtpCodeMail($code, max(1, (int) ceil($ttl / 60))));
            } catch (Throwable $e) {
                Log::warning('OTP e-mail failed', ['otp_challenge_id' => $challenge->id, 'error' => $e->getMessage()]);
            }
        }

        $this->audit->log('otp.issued', $challenge, [], ['customer_id' => $customer->id, 'expires_at' => $challenge->expires_at->toIso8601String()]);

        return $challenge;
    }

    /**
     * Verify a code. Success releases a held transaction (-> completed). After the maximum number of wrong
     * attempts the challenge fails and the held transaction is rejected. Expired challenges never verify.
     */
    public function verify(OtpChallenge $challenge, string $code): bool
    {
        return DB::transaction(function () use ($challenge, $code) {
            $challenge = OtpChallenge::query()->lockForUpdate()->findOrFail($challenge->id);

            if ($challenge->status !== OtpStatus::Pending) {
                return false;
            }

            if ($challenge->expires_at->isPast()) {
                $challenge->update(['status' => OtpStatus::Expired]);
                $this->audit->log('otp.expired', $challenge, [], ['status' => OtpStatus::Expired->value]);

                return false;
            }

            $challenge->attempts++;

            if (Hash::check($code, $challenge->code_hash)) {
                $challenge->fill(['status' => OtpStatus::Verified, 'verified_at' => now()])->save();
                $this->releaseTransaction($challenge);
                $this->audit->log('otp.verified', $challenge, [], ['attempts' => $challenge->attempts]);

                return true;
            }

            if ($challenge->attempts >= $this->maxAttempts()) {
                $challenge->status = OtpStatus::Failed;
                $challenge->save();
                $this->rejectTransaction($challenge);
                $this->audit->log('otp.failed', $challenge, [], ['attempts' => $challenge->attempts, 'reason' => 'max_attempts']);

                return false;
            }

            $challenge->save();

            return false;
        });
    }

    public function remainingAttempts(OtpChallenge $challenge): int
    {
        return max(0, $this->maxAttempts() - $challenge->attempts);
    }

    private function maxAttempts(): int
    {
        return (int) $this->settings->get('otp.max_attempts', 3);
    }

    private function releaseTransaction(OtpChallenge $challenge): void
    {
        $tx = $challenge->transaction;
        if ($tx && $tx->status === TransactionStatus::Held) {
            $tx->update(['status' => TransactionStatus::Completed]);
        }
    }

    private function rejectTransaction(OtpChallenge $challenge): void
    {
        $tx = $challenge->transaction;
        if ($tx && $tx->status === TransactionStatus::Held) {
            $tx->update(['status' => TransactionStatus::Rejected]);
            $this->audit->log('transaction.rejected_otp_failed', $tx, ['status' => TransactionStatus::Held->value], ['status' => TransactionStatus::Rejected->value]);
        }
    }
}
