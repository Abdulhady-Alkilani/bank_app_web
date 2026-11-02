<?php

namespace App\Data;

use App\Enums\EventType;
use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * The stored subject (transaction or account event) plus the ingestion context needed by the feature service.
 */
final readonly class IngestedEvent
{
    public function __construct(
        public EventType $eventType,
        public Account $account,
        public Customer $customer,
        public Transaction|AccountEvent $subject,
        public Carbon $eventTime,
        public ?Device $device = null,
        public bool $isNewDevice = false,
        public ?Beneficiary $beneficiary = null,
        public bool $beneficiaryCreated = false,
        public ?string $ip = null,
        public ?string $city = null,
        public ?string $previousLocation = null,
        public bool $replayed = false,
    ) {}

    public function transaction(): ?Transaction
    {
        return $this->subject instanceof Transaction ? $this->subject : null;
    }

    public function event(): ?AccountEvent
    {
        return $this->subject instanceof AccountEvent ? $this->subject : null;
    }
}
