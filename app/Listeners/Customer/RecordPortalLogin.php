<?php

namespace App\Listeners\Customer;

use App\Contracts\ScoringPipeline;
use App\Enums\EventType;
use App\Models\User;
use App\Services\Customer\SimulationDevice;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records a `login` event for customers who sign in to the portal. Never breaks the sign-in.
 */
class RecordPortalLogin
{
    public function __construct(
        private readonly ScoringPipeline $pipeline,
        private readonly SimulationDevice $device,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->hasRole('customer')) {
            return;
        }

        try {
            $customer = $user->customer;
            $account = $customer?->accounts()->orderBy('id')->first();

            // A frozen account would only produce a new alert for every sign-in.
            if (! $account || $account->isFrozen()) {
                return;
            }

            $device = $this->device->current($customer);

            $this->pipeline->process([
                'event_type' => EventType::Login->value,
                'account_number' => $account->account_number,
                'device_fingerprint' => $device['fingerprint'],
                'device_type' => $device['device_type'],
                'ip_address' => $device['ip'],
                'city' => $device['city'],
                'channel' => 'portal',
                'event_time' => now()->toIso8601String(),
                'idempotency_key' => 'portal-login-'.Str::uuid(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Portal login scoring failed', ['user_id' => $user->getKey(), 'error' => $e->getMessage()]);
        }
    }
}
