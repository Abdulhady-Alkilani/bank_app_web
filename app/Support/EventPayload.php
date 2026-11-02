<?php

namespace App\Support;

use App\Enums\EventType;
use Illuminate\Validation\Rule;

/**
 * Validation contract shared by POST /events, POST /score and EventIngestor (used directly by the customer portal).
 */
class EventPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?string $eventType = null): array
    {
        $isTransfer = $eventType === EventType::Transfer->value;
        $needsBeneficiary = in_array($eventType, [EventType::Transfer->value, EventType::AddBeneficiary->value], true);

        return [
            'event_type' => ['required', Rule::enum(EventType::class)],
            'account_number' => ['required', 'string', 'max:64'],
            'event_time' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'ip_address' => ['nullable', 'ip'],
            'city' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:30'],
            'amount' => [$isTransfer ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:1000000000000'],
            'beneficiary_account' => [$needsBeneficiary ? 'required' : 'nullable', 'string', 'max:64'],
            'beneficiary_name' => ['nullable', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'new_phone' => ['nullable', 'string', 'max:30'],
            'new_limit' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'details' => ['nullable', 'array'],
        ];
    }
}
