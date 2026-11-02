<div class="space-y-4">
    @if ($message)
        <x-console.flash :message="$message" />
    @endif

    <p class="text-sm text-slate-500">{{ __('customer.devices.intro') }}</p>

    @if ($links->isEmpty())
        <x-ui.empty-state :title="__('customer.devices.none')" />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('customer.devices.device') }}</th>
                <th>{{ __('customer.devices.last_ip') }}</th>
                <th>{{ __('customer.devices.last_used') }}</th>
                <th>{{ __('common.status') }}</th>
                <th>{{ __('common.actions') }}</th>
            </x-slot:head>
            @foreach ($links as $link)
                <tr wire:key="device-{{ $link->id }}">
                    <td>
                        <span class="block font-mono text-xs" dir="ltr">{{ $link->device?->device_fingerprint }}</span>
                        <span class="text-xs text-slate-400">{{ $link->device?->device_type }}</span>
                    </td>
                    <td dir="ltr">{{ $link->device?->last_ip }} <span class="text-xs text-slate-400">{{ $link->device?->last_location }}</span></td>
                    <td class="whitespace-nowrap text-slate-500">{{ $link->last_used_at?->format('Y-m-d H:i') }}</td>
                    <td><x-ui.badge :color="$link->is_trusted ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'" :label="$link->is_trusted ? __('customer.devices.trusted') : __('customer.devices.untrusted_label')" /></td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @if ($link->is_trusted)
                                <x-ui.button variant="secondary" class="!px-2 !py-1 text-xs" wire:click="untrust({{ $link->id }})">{{ __('customer.devices.untrust') }}</x-ui.button>
                            @endif
                            <x-ui.button variant="danger" class="!px-2 !py-1 text-xs" wire:click="remove({{ $link->id }})" wire:confirm="{{ __('customer.devices.confirm_remove') }}">{{ __('customer.devices.remove') }}</x-ui.button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</div>
