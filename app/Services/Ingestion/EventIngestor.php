<?php

namespace App\Services\Ingestion;

use App\Data\IngestedEvent;
use App\Enums\AccountStatus;
use App\Enums\EventType;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\AccountEvent;
use App\Models\Beneficiary;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\Device;
use App\Models\Transaction;
use App\Services\Settings\SettingsService;
use App\Support\EventPayload;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EventIngestor
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Validate, normalise and store an event. Replaying an idempotency key returns the stored subject
     * (IngestedEvent::$replayed = true) instead of duplicating it.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function ingest(array $payload): IngestedEvent
    {
        $data = Validator::make($payload, EventPayload::rules($payload['event_type'] ?? null))->validate();
        $key = $data['idempotency_key'] ?? null;

        if ($key && ($existing = $this->find($key))) {
            return $this->rehydrate($existing);
        }

        try {
            return DB::transaction(fn () => $this->store($data));
        } catch (UniqueConstraintViolationException $e) {
            if ($key && ($existing = $this->find($key))) {
                return $this->rehydrate($existing);
            }

            throw $e;
        }
    }

    private function find(string $key): Transaction|AccountEvent|null
    {
        return Transaction::where('idempotency_key', $key)->first()
            ?? AccountEvent::where('idempotency_key', $key)->first();
    }

    private function rehydrate(Transaction|AccountEvent $subject): IngestedEvent
    {
        $account = $subject->account()->with('customer')->firstOrFail();

        return new IngestedEvent(
            eventType: $subject instanceof Transaction ? EventType::Transfer : $subject->event_type,
            account: $account,
            customer: $account->customer,
            subject: $subject,
            eventTime: Carbon::parse($subject instanceof Transaction ? $subject->transaction_time : $subject->event_time),
            device: $subject->device,
            beneficiary: $subject instanceof Transaction ? $subject->beneficiary : null,
            ip: $subject->ip_address,
            city: $subject instanceof Transaction ? $subject->city : Arr::get($subject->details ?? [], 'city'),
            replayed: true,
        );
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function store(array $d): IngestedEvent
    {
        $type = EventType::from($d['event_type']);
        $time = isset($d['event_time']) ? Carbon::parse($d['event_time']) : now();
        $details = (array) ($d['details'] ?? []);

        $account = Account::with('customer')->where('account_number', $d['account_number'])->first();
        if (! $account) {
            if ($type !== EventType::OpenAccount) {
                throw ValidationException::withMessages(['account_number' => __('validation.exists', ['attribute' => 'account number'])]);
            }
            $account = $this->openAccount($d, $details);
        }
        $customer = $account->customer;

        [$device, $isNewDevice, $previousLocation] = $this->resolveDevice($d, $customer, $type, $time);

        $beneficiary = null;
        $beneficiaryCreated = false;
        if (in_array($type, [EventType::Transfer, EventType::AddBeneficiary], true) && ! empty($d['beneficiary_account'])) {
            [$beneficiary, $beneficiaryCreated] = $this->resolveBeneficiary($account, $d, $time);
        }

        if ($type === EventType::Transfer) {
            $subject = Transaction::create([
                'account_id' => $account->id,
                'beneficiary_id' => $beneficiary?->id,
                'device_id' => $device?->id,
                'amount' => $d['amount'],
                'channel' => $d['channel'] ?? 'mobile',
                'ip_address' => $d['ip_address'] ?? null,
                'city' => $d['city'] ?? null,
                'status' => TransactionStatus::Pending,
                'transaction_time' => $time,
                'idempotency_key' => $d['idempotency_key'] ?? null,
            ]);
        } else {
            $subject = AccountEvent::create([
                'account_id' => $account->id,
                'device_id' => $device?->id,
                'event_type' => $type,
                'ip_address' => $d['ip_address'] ?? null,
                'details' => $this->eventDetails($type, $d, $details, $account, $beneficiary),
                'event_time' => $time,
                'idempotency_key' => $d['idempotency_key'] ?? null,
            ]);
            $this->applySideEffects($type, $d, $details, $account, $customer, $device, $time);
        }

        return new IngestedEvent(
            eventType: $type,
            account: $account->refresh()->setRelation('customer', $customer->refresh()),
            customer: $customer,
            subject: $subject,
            eventTime: $time,
            device: $device,
            isNewDevice: $isNewDevice,
            beneficiary: $beneficiary,
            beneficiaryCreated: $beneficiaryCreated,
            ip: $d['ip_address'] ?? null,
            city: $d['city'] ?? null,
            previousLocation: $previousLocation,
        );
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, mixed>  $details
     */
    private function openAccount(array $d, array $details): Account
    {
        $customer = null;
        if (! empty($d['customer_id'])) {
            $customer = Customer::find($d['customer_id']);
        }
        if (! $customer && ! empty($d['customer_email'])) {
            $customer = Customer::where('email', $d['customer_email'])->first();
        }
        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => 'open_account requires an existing customer_id or customer_email.']);
        }

        $account = Account::create([
            'customer_id' => $customer->id,
            'account_number' => $d['account_number'],
            'type' => $details['account_type'] ?? 'current',
            'status' => AccountStatus::Active,
            'balance' => $details['initial_balance'] ?? 0,
            'transfer_limit' => $this->settings->get('transfer.default_limit', 20000),
        ]);

        return $account->setRelation('customer', $customer);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{0: ?Device, 1: bool, 2: ?string}
     */
    private function resolveDevice(array $d, Customer $customer, EventType $type, Carbon $time): array
    {
        if (empty($d['device_fingerprint'])) {
            return [null, false, null];
        }

        $device = Device::firstOrNew(['device_fingerprint' => $d['device_fingerprint']]);
        $previousLocation = $device->last_location;
        $device->device_type = $d['device_type'] ?? $device->device_type;
        $device->last_ip = $d['ip_address'] ?? $device->last_ip;
        $device->last_location = $d['city'] ?? $device->last_location;
        if ($type === EventType::ChangePhone) {
            $device->sim_changed_at = $time;
        }
        $device->save();

        $link = CustomerDevice::firstOrNew(['customer_id' => $customer->id, 'device_id' => $device->id]);
        $created = ! $link->exists;
        if ($created) {
            // The very first device of a customer is the baseline and therefore trusted.
            $link->is_trusted = ! CustomerDevice::where('customer_id', $customer->id)->exists();
        }
        $link->last_used_at = $time;
        $link->save();

        $isNew = ! $link->is_trusted && ($created || $link->created_at?->gte(now()->subDay()));

        return [$device, (bool) $isNew, $previousLocation];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{0: Beneficiary, 1: bool}
     */
    private function resolveBeneficiary(Account $account, array $d, Carbon $time): array
    {
        $beneficiary = Beneficiary::firstOrNew([
            'account_id' => $account->id,
            'beneficiary_account' => $d['beneficiary_account'],
        ]);
        $created = ! $beneficiary->exists;
        if ($created) {
            $beneficiary->fill([
                'beneficiary_name' => $d['beneficiary_name'] ?? null,
                'bank_name' => $d['bank_name'] ?? null,
                'added_at' => $time,
            ]);
            $beneficiary->save();
        }

        return [$beneficiary, $created];
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function eventDetails(EventType $type, array $d, array $details, Account $account, ?Beneficiary $beneficiary): array
    {
        $extra = match ($type) {
            EventType::AddBeneficiary => [
                'beneficiary_account' => $d['beneficiary_account'] ?? null,
                'beneficiary_name' => $d['beneficiary_name'] ?? null,
                'bank_name' => $d['bank_name'] ?? null,
            ],
            EventType::ChangePhone => [
                'old_phone' => $account->customer?->phone,
                'new_phone' => $d['new_phone'] ?? $details['new_phone'] ?? null,
            ],
            EventType::RaiseLimit => [
                'old_limit' => $account->transfer_limit,
                'new_limit' => $d['new_limit'] ?? $details['new_limit'] ?? null,
            ],
            EventType::OpenAccount => ['account_number' => $account->account_number],
            default => [],
        };

        return array_filter(
            $details + $extra + ['city' => $d['city'] ?? null, 'channel' => $d['channel'] ?? null],
            fn ($v) => $v !== null,
        );
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, mixed>  $details
     */
    private function applySideEffects(EventType $type, array $d, array $details, Account $account, Customer $customer, ?Device $device, Carbon $time): void
    {
        match ($type) {
            EventType::ChangePhone => ($phone = $d['new_phone'] ?? $details['new_phone'] ?? null)
                ? $customer->update(['phone' => $phone]) : null,
            EventType::RaiseLimit => ($limit = $d['new_limit'] ?? $details['new_limit'] ?? null) !== null
                ? $account->update(['transfer_limit' => $limit]) : null,
            default => null,
        };
    }
}
