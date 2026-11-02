@php
    $field = 'block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none';
    // Small helper: links for an account row.
@endphp
<div class="space-y-4">
    <x-ui.card>
        <label for="q" class="mb-1 block text-sm font-medium text-slate-700">{{ __('analyst.search.label') }}</label>
        <input id="q" type="search" wire:model.live.debounce.400ms="q" class="{{ $field }}" placeholder="{{ __('analyst.search.placeholder') }}" autofocus>
        <p class="mt-1 text-xs text-slate-400">{{ __('analyst.search.hint') }}</p>
    </x-ui.card>

    @if ($term === '')
        <x-ui.empty-state :title="__('analyst.search.start')" />
    @elseif ($total === 0)
        <x-ui.empty-state :title="__('analyst.search.none')" />
    @else
        @if ($results['accounts']->isNotEmpty())
            <x-ui.card :title="__('analyst.search.accounts')" :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($results['accounts'] as $account)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3" wire:key="sa-{{ $account->id }}">
                            <span class="font-mono">{{ $account->account_number }}</span>
                            <x-ui.badge :value="$account->status" />
                            <span class="flex-1"></span>
                            @if ($account->customer)<a class="text-brand-700 hover:underline" href="{{ route('analyst.customers.show', $account->customer) }}" wire:navigate>{{ __('analyst.alert.customer_profile') }}</a>@endif
                            <a class="text-brand-700 hover:underline" href="{{ route('analyst.accounts.network', $account) }}" wire:navigate>{{ __('analyst.alert.network') }}</a>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($results['devices']->isNotEmpty())
            <x-ui.card :title="__('analyst.search.devices')" :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($results['devices'] as $row)
                        <li class="px-5 py-3" wire:key="sd-{{ $row['device']->id }}">
                            <p class="font-mono text-xs">{{ $row['device']->device_fingerprint }} <span class="text-slate-400">({{ $row['device']->device_type }})</span></p>
                            <div class="mt-1 flex flex-wrap gap-3">
                                @foreach ($row['accounts'] as $account)
                                    <a class="text-brand-700 hover:underline" href="{{ route('analyst.accounts.network', $account) }}" wire:navigate>{{ $account->account_number }}</a>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($results['ips']->isNotEmpty())
            <x-ui.card :title="__('analyst.search.ips')" :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($results['ips'] as $row)
                        <li class="px-5 py-3" wire:key="si-{{ $row['ip'] }}">
                            <p class="font-mono text-xs" dir="ltr">{{ $row['ip'] }}</p>
                            <div class="mt-1 flex flex-wrap gap-3">
                                @foreach ($row['accounts'] as $account)
                                    <a class="text-brand-700 hover:underline" href="{{ route('analyst.accounts.network', $account) }}" wire:navigate>{{ $account->account_number }}</a>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($results['beneficiaries']->isNotEmpty())
            <x-ui.card :title="__('analyst.search.beneficiaries')" :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($results['beneficiaries'] as $b)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3" wire:key="sb-{{ $b->id }}">
                            <span class="font-mono">{{ $b->beneficiary_account }}</span>
                            <span class="text-slate-500">{{ $b->beneficiary_name }}</span>
                            @if ($b->is_frozen)<x-ui.badge :label="__('analyst.alert.frozen')" color="bg-red-100 text-red-800" />@endif
                            <span class="flex-1"></span>
                            @if ($b->account)<a class="text-brand-700 hover:underline" href="{{ route('analyst.accounts.network', $b->account) }}" wire:navigate>{{ $b->account->account_number }}</a>@endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($results['transactions']->isNotEmpty())
            <x-ui.card :title="__('analyst.search.transactions')" :padded="false">
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($results['transactions'] as $t)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3" wire:key="st-{{ $t->id }}">
                            <span>#{{ $t->id }}</span>
                            <span class="tabular-nums">{{ number_format((float) $t->amount, 2) }}</span>
                            <x-ui.badge :value="$t->status" />
                            <span class="font-mono text-xs">{{ $t->account?->account_number }}</span>
                            <span class="flex-1"></span>
                            @if ($t->riskScore?->alert && auth()->user()->can('view', $t->riskScore->alert))
                                <a class="text-brand-700 hover:underline" href="{{ route('analyst.alerts.show', $t->riskScore->alert) }}" wire:navigate>{{ __('analyst.search.alert_link', ['id' => $t->riskScore->alert->id]) }}</a>
                            @endif
                            @if ($t->account?->customer)<a class="text-brand-700 hover:underline" href="{{ route('analyst.customers.show', $t->account->customer) }}" wire:navigate>{{ __('analyst.alert.customer_profile') }}</a>@endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    @endif
</div>
