<?php

namespace App\Livewire\Customer;

use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Services\Customer\SimulationDevice;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Simulation device panel: the device / IP / city the portal pretends the customer is using.
 */
class DevicePanel extends Component
{
    use InteractsWithCustomer;

    public string $fingerprint = '';

    public string $ip = '';

    public string $city = '';

    public bool $isNew = false;

    public bool $saved = false;

    public function mount(SimulationDevice $device): void
    {
        $this->load($device->current($this->customer()));
    }

    public function save(SimulationDevice $device): void
    {
        $this->validate([
            'fingerprint' => ['required', 'string', 'max:255'],
            'ip' => ['required', 'ip'],
            'city' => ['required', 'string', 'max:100'],
        ]);

        $current = $device->current($this->customer());
        $device->save([
            'fingerprint' => $this->fingerprint,
            'device_type' => $current['device_type'],
            'ip' => $this->ip,
            'city' => $this->city,
            'is_new' => $this->fingerprint !== $device->defaults($this->customer())['fingerprint'],
        ]);

        $this->load($device->current($this->customer()));
        $this->saved = true;
    }

    public function useNewDevice(SimulationDevice $device): void
    {
        $this->load($device->useNewDevice($this->customer()));
        $this->saved = true;
    }

    public function useTrustedDevice(SimulationDevice $device): void
    {
        $device->reset();
        $this->load($device->current($this->customer()));
        $this->saved = true;
    }

    /**
     * @param  array{fingerprint: string, ip: string, city: string, is_new: bool}  $device
     */
    private function load(array $device): void
    {
        $this->fingerprint = $device['fingerprint'];
        $this->ip = $device['ip'];
        $this->city = $device['city'];
        $this->isNew = (bool) $device['is_new'];
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.customer.device-panel');
    }
}
