<?php

namespace App\Services\Customer;

use App\Contracts\ScoringPipeline;
use App\Data\ScoringResult;
use App\Enums\Decision;
use App\Enums\EventType;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Builds the event payload for a customer action using the current simulation device and runs it
 * through the scoring pipeline (the same path the bank's real channels use).
 */
class PortalOperations
{
    public function __construct(
        private readonly ScoringPipeline $pipeline,
        private readonly SimulationDevice $device,
    ) {}

    /**
     * @param  array<string, mixed>  $data  extra payload fields (amount, beneficiary_account, new_phone, new_limit ...)
     */
    public function submit(Customer $customer, Account $account, EventType $type, array $data = []): ScoringResult
    {
        abort_unless($account->customer_id === $customer->id, 403);

        $device = $this->device->current($customer);

        $payload = array_filter([
            'event_type' => $type->value,
            'account_number' => $account->account_number,
            'device_fingerprint' => $device['fingerprint'],
            'device_type' => $device['device_type'],
            'ip_address' => $device['ip'],
            'city' => $device['city'],
            'channel' => 'portal',
            'event_time' => now()->toIso8601String(),
            'idempotency_key' => 'portal-'.Str::uuid(),
        ] + $data, fn ($value) => $value !== null && $value !== '');

        $result = $this->pipeline->process($payload);

        if ($result->decision === Decision::Allow && $result->subject instanceof Transaction) {
            $this->debit($result->subject);
        }

        return $result;
    }

    /**
     * Move the money of a completed transfer out of the sender's balance.
     */
    public function debit(Transaction $transaction): void
    {
        Account::query()->whereKey($transaction->account_id)->decrement('balance', (float) $transaction->amount);
    }
}
