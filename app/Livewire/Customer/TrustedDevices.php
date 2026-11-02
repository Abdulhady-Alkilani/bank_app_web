<?php

namespace App\Livewire\Customer;

use App\Livewire\Customer\Concerns\InteractsWithCustomer;
use App\Models\CustomerDevice;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * CUS-06: devices linked to the customer; untrust or remove a device (audited).
 */
class TrustedDevices extends Component
{
    use InteractsWithCustomer;

    public ?string $message = null;

    public function untrust(int $linkId, AuditLogger $audit): void
    {
        $link = $this->ownedLink($linkId);

        if ($link->is_trusted) {
            $link->update(['is_trusted' => false]);
            $audit->log('customer_device.untrusted', $link, ['is_trusted' => true], ['is_trusted' => false]);
        }

        $this->message = __('customer.devices.untrusted');
    }

    public function remove(int $linkId, AuditLogger $audit): void
    {
        $link = $this->ownedLink($linkId);
        $old = ['device_id' => $link->device_id, 'is_trusted' => $link->is_trusted];

        $audit->log('customer_device.removed', $link, $old, []);
        $link->delete();

        $this->message = __('customer.devices.removed');
    }

    private function ownedLink(int $id): CustomerDevice
    {
        return $this->customer()->customerDevices()->whereKey($id)->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.customer.trusted-devices', [
            'links' => $this->customer()->customerDevices()->with('device')->orderByDesc('is_trusted')->latest('last_used_at')->get(),
        ])->title(__('customer.devices.title'));
    }
}
