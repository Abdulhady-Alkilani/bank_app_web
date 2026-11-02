<div class="space-y-6">
    <div>
        <h2 class="mb-2 text-sm font-semibold text-slate-700">{{ __('customer.activity.transactions') }}</h2>
        <livewire:customer.transaction-list :limit="25" />
    </div>

    <div>
        <h2 class="mb-2 text-sm font-semibold text-slate-700">{{ __('customer.activity.logins') }}</h2>
        @if ($logins->isEmpty())
            <x-ui.empty-state :title="__('customer.activity.no_logins')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>{{ __('customer.activity.time') }}</th>
                    <th>{{ __('customer.activity.device') }}</th>
                    <th>{{ __('customer.activity.ip') }}</th>
                    <th>{{ __('customer.activity.city') }}</th>
                </x-slot:head>
                @foreach ($logins as $login)
                    <tr wire:key="login-{{ $login->id }}">
                        <td class="whitespace-nowrap text-slate-500">{{ $login->event_time?->format('Y-m-d H:i') }}</td>
                        <td class="font-mono text-xs" dir="ltr">{{ $login->device?->device_fingerprint ?? '-' }}</td>
                        <td dir="ltr">{{ $login->ip_address ?? '-' }}</td>
                        <td>{{ $login->details['city'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </div>
</div>
