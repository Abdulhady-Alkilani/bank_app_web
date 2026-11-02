<?php

namespace App\Services\Customer;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * The "simulation device" of the customer portal: the device / IP / city the portal pretends the customer is
 * using. It is stored in the session and attached to every event the portal sends to the scoring pipeline, so a
 * demo can switch to an unknown device and reproduce the account takeover.
 */
class SimulationDevice
{
    public const SESSION_KEY = 'portal.sim_device';

    public const FOREIGN_IP = '185.220.101.7';

    public const FOREIGN_CITY = 'Moscow';

    /**
     * @return array{fingerprint: string, device_type: string, ip: string, city: string, is_new: bool}
     */
    public function current(Customer $customer): array
    {
        $stored = session(self::SESSION_KEY);
        $defaults = $this->defaults($customer);

        if (is_array($stored) && filled($stored['fingerprint'] ?? null)) {
            return array_merge($defaults, array_intersect_key($stored, $defaults));
        }

        return $defaults;
    }

    /**
     * @return array{fingerprint: string, device_type: string, ip: string, city: string, is_new: bool}
     */
    public function defaults(Customer $customer): array
    {
        $trusted = $customer->customerDevices()->where('is_trusted', true)->with('device')->orderBy('id')->first()?->device;
        $ip = $trusted?->last_ip;

        return [
            'fingerprint' => $trusted?->device_fingerprint ?? 'portal-web-'.$customer->id,
            'device_type' => $trusted?->device_type ?? 'web',
            'ip' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '10.10.0.5',
            'city' => (string) (data_get($customer->usage_pattern, 'cities.0') ?: ($trusted?->last_location ?: 'Sanaa')),
            'is_new' => false,
        ];
    }

    /**
     * @param  array{fingerprint?: string, device_type?: string, ip?: string, city?: string, is_new?: bool}  $device
     */
    public function save(array $device): void
    {
        session([self::SESSION_KEY => $device]);
    }

    /**
     * Switch to a brand-new, unknown device on a foreign network.
     *
     * @return array{fingerprint: string, device_type: string, ip: string, city: string, is_new: bool}
     */
    public function useNewDevice(Customer $customer): array
    {
        $device = [
            'fingerprint' => 'portal-unknown-'.Str::lower(Str::random(8)),
            'device_type' => 'android',
            'ip' => self::FOREIGN_IP,
            'city' => self::FOREIGN_CITY,
            'is_new' => true,
        ];
        $this->save($device);

        return $device;
    }

    public function reset(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
