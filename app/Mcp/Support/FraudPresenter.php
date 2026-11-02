<?php

namespace App\Mcp\Support;

use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\Device;
use App\Models\RiskScore;
use App\Models\Transaction;
use App\Support\Masker;

/**
 * Turns models into plain arrays with every sensitive value masked (AI-06).
 */
class FraudPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function account(Account $account): array
    {
        return [
            'id' => $account->id,
            'account_number' => Masker::maskAccount($account->account_number),
            'type' => $account->type,
            'status' => $account->status->value,
            'balance' => (float) $account->balance,
            'transfer_limit' => (float) $account->transfer_limit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function customer(?Customer $customer): ?array
    {
        if (! $customer) {
            return null;
        }

        return [
            'id' => $customer->id,
            'name' => Masker::maskName($customer->name),
            'phone' => Masker::maskPhone($customer->phone),
            'email' => Masker::maskEmail($customer->email),
            'avg_balance' => (float) $customer->avg_balance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function beneficiary(?Beneficiary $beneficiary): ?array
    {
        if (! $beneficiary) {
            return null;
        }

        return [
            'id' => $beneficiary->id,
            'account' => Masker::maskAccount($beneficiary->beneficiary_account),
            'name' => Masker::maskName($beneficiary->beneficiary_name),
            'bank_name' => $beneficiary->bank_name,
            'added_at' => $beneficiary->added_at?->toIso8601String(),
            'is_frozen' => (bool) $beneficiary->is_frozen,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function transaction(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'time' => $transaction->transaction_time?->toIso8601String(),
            'amount' => (float) $transaction->amount,
            'channel' => $transaction->channel,
            'city' => $transaction->city,
            'ip' => Masker::maskIp($transaction->ip_address),
            'status' => $transaction->status->value,
            'device_id' => $transaction->device_id,
            'beneficiary' => self::beneficiary($transaction->beneficiary),
            'risk_score' => $transaction->riskScore?->score,
            'decision' => $transaction->riskScore?->decision?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function event(AccountEvent $event): array
    {
        return [
            'id' => $event->id,
            'time' => $event->event_time?->toIso8601String(),
            'type' => $event->event_type->value,
            'ip' => Masker::maskIp($event->ip_address),
            'details' => Masker::maskArray((array) ($event->details ?? []), ['phone', 'new_phone', 'old_phone', 'email', 'ip', 'beneficiary_account', 'account_number', 'beneficiary_name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function device(Device $device): array
    {
        return [
            'id' => $device->id,
            'fingerprint' => substr((string) $device->device_fingerprint, 0, 12),
            'type' => $device->device_type,
            'last_ip' => Masker::maskIp($device->last_ip),
            'last_location' => $device->last_location,
            'sim_changed_at' => $device->sim_changed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function riskScore(?RiskScore $score): ?array
    {
        if (! $score) {
            return null;
        }

        return [
            'id' => $score->id,
            'score' => $score->score,
            'decision' => $score->decision->value,
            'rule_score' => $score->rule_score,
            'model_probability' => $score->model_probability !== null ? (float) $score->model_probability : null,
            'used_fallback' => (bool) $score->used_fallback,
            'latency_ms' => $score->latency_ms,
            'evaluated_at' => $score->evaluated_at?->toIso8601String(),
            'model' => $score->modelRun ? [
                'name' => $score->modelRun->model?->model_name,
                'version' => $score->modelRun->version_number,
            ] : null,
        ];
    }
}
