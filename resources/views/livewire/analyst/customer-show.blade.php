@php
    use App\Support\Masker;
    $name = $revealed ? $customer->name : Masker::maskName($customer->name);
    $phone = $revealed ? $customer->phone : Masker::maskPhone($customer->phone);
    $email = $revealed ? $customer->email : Masker::maskEmail($customer->email);
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        @if ($alert)
            <x-ui.button variant="ghost" :href="route('analyst.alerts.show', $alert)" wire:navigate>&larr; {{ __('analyst.customer.back_to_alert') }}</x-ui.button>
        @endif
        <h2 class="text-lg font-semibold">{{ $name }}</h2>
        @if ($revealed)
            <x-ui.button variant="secondary" wire:click="hide" class="!px-3 !py-1 text-xs">{{ __('analyst.customer.hide') }}</x-ui.button>
        @else
            <x-ui.button variant="secondary" wire:click="reveal" wire:confirm="{{ __('analyst.customer.reveal_confirm') }}" class="!px-3 !py-1 text-xs">{{ __('analyst.customer.reveal') }}</x-ui.button>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card :title="__('analyst.customer.identity')">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.customer.phone') }}</dt><dd class="font-mono" dir="ltr">{{ $phone }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.customer.email') }}</dt><dd class="font-mono text-xs" dir="ltr">{{ $email }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.customer.avg_balance') }}</dt><dd class="tabular-nums">{{ number_format((float) $customer->avg_balance, 2) }}</dd></div>
            </dl>
            <div class="mt-4 space-y-2">
                @foreach ($customer->accounts as $account)
                    <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 p-2 text-sm" wire:key="acc-{{ $account->id }}">
                        <span class="font-mono text-xs">{{ $revealed ? $account->account_number : Masker::maskAccount($account->account_number) }}</span>
                        <x-ui.badge :value="$account->status" />
                        <a href="{{ route('analyst.accounts.network', $account) }}" wire:navigate class="text-xs text-brand-700 hover:underline">{{ __('analyst.alert.network') }}</a>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card :title="__('analyst.customer.usual_patterns')" class="lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-4">
                <div><dt class="text-xs text-slate-500">{{ __('analyst.customer.avg_amount') }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($patterns['avg_amount'], 2) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('analyst.customer.max_amount') }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($patterns['max_amount'], 2) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('analyst.customer.usual_hours') }}</dt><dd>{{ $patterns['hours'] ? collect($patterns['hours'])->map(fn ($h) => sprintf('%02d:00', $h))->implode(', ') : '—' }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('analyst.customer.usual_cities') }}</dt><dd>{{ $patterns['cities'] ? implode(', ', $patterns['cities']) : '—' }}</dd></div>
            </dl>

            @if ($comparison)
                <h3 class="mb-2 mt-5 text-sm font-semibold text-slate-700">{{ __('analyst.customer.vs_current') }}</h3>
                <x-ui.table>
                    <x-slot:head><th></th><th>{{ __('analyst.customer.current') }}</th><th>{{ __('analyst.customer.usual') }}</th><th></th></x-slot:head>
                    @foreach ($comparison as $row)
                        <tr>
                            <td class="font-medium">{{ $row['label'] }}</td>
                            <td class="tabular-nums">{{ $row['current'] }}</td>
                            <td class="text-slate-600">{{ $row['usual'] }}</td>
                            <td>@if ($row['unusual'])<x-ui.badge :label="__('analyst.customer.unusual')" color="bg-red-100 text-red-800" />@endif</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('analyst.customer.devices')" :padded="false">
            @if ($customer->customerDevices->isEmpty())
                <p class="p-5 text-sm text-slate-500">{{ __('analyst.customer.no_devices') }}</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($customer->customerDevices as $cd)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5" wire:key="dev-{{ $cd->id }}">
                            <span class="font-mono text-xs">{{ substr($cd->device?->device_fingerprint ?? '', 0, 16) }}</span>
                            <span class="text-slate-500">{{ $cd->device?->device_type }}</span>
                            @if ($cd->is_trusted)<x-ui.badge :label="__('analyst.customer.trusted')" color="bg-emerald-100 text-emerald-800" />@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('analyst.customer.alerts')" :padded="false">
            @if ($alerts->isEmpty())
                <p class="p-5 text-sm text-slate-500">{{ __('analyst.customer.no_alerts') }}</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($alerts as $a)
                        <li wire:key="al-{{ $a->id }}">
                            <a href="{{ route('analyst.alerts.show', $a) }}" wire:navigate class="flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50">
                                <x-analyst.score :score="$a->riskScore?->score ?? 0" />
                                <span class="flex-1">#{{ $a->id }}</span>
                                <x-ui.badge :value="$a->status" />
                                <span class="text-xs text-slate-400">{{ $a->created_at?->diffForHumans() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card :title="__('analyst.customer.history')" :padded="false">
        @if ($transactions->isEmpty())
            <p class="p-5 text-sm text-slate-500">{{ __('analyst.customer.no_history') }}</p>
        @else
            <x-ui.table class="border-0 shadow-none">
                <x-slot:head>
                    <th>{{ __('analyst.customer.time') }}</th><th>{{ __('analyst.queue.account') }}</th><th>{{ __('analyst.queue.amount') }}</th>
                    <th>{{ __('analyst.queue.channel') }}</th><th>{{ __('analyst.customer.city') }}</th><th>{{ __('analyst.queue.status') }}</th><th>{{ __('analyst.queue.score') }}</th>
                </x-slot:head>
                @foreach ($transactions as $t)
                    <tr wire:key="tx-{{ $t->id }}">
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $t->transaction_time?->format('Y-m-d H:i') }}</td>
                        <td class="font-mono text-xs">{{ $revealed ? $t->account?->account_number : Masker::maskAccount($t->account?->account_number) }}</td>
                        <td class="tabular-nums">{{ number_format((float) $t->amount, 2) }}</td>
                        <td>{{ $t->channel }}</td>
                        <td>{{ $t->city }}</td>
                        <td><x-ui.badge :value="$t->status" /></td>
                        <td>@if ($t->riskScore)<x-analyst.score :score="$t->riskScore->score" />@endif</td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
