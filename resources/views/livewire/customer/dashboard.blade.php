<div class="space-y-6">
    @include('livewire.customer.partials.frozen-banner', ['frozen' => $frozen])

    <div>
        <h2 class="text-xl font-bold">{{ __('customer.dashboard.welcome', ['name' => $customer->name]) }}</h2>
        <p class="text-sm text-slate-500">{{ __('customer.dashboard.subtitle') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($accounts as $account)
            <x-ui.card :padded="false">
                <div class="space-y-2 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-slate-500">{{ __('customer.accounts.number') }}: <span class="font-mono" dir="ltr">{{ $account->account_number }}</span></p>
                        <x-ui.badge :color="$account->isFrozen() ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'" :label="$account->status->label()" />
                    </div>
                    <p class="text-3xl font-bold" dir="ltr">{{ number_format((float) $account->balance, 2) }}</p>
                    <p class="text-xs text-slate-400">{{ __('customer.accounts.balance') }}</p>
                </div>
            </x-ui.card>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        <x-ui.button :href="route('portal.transfer')">{{ __('customer.dashboard.new_transfer') }}</x-ui.button>
        <x-ui.button variant="secondary" :href="route('portal.history')">{{ __('customer.activity.title') }}</x-ui.button>
        <x-ui.button variant="secondary" :href="route('portal.devices')">{{ __('customer.devices.title') }}</x-ui.button>
        <x-ui.button variant="secondary" :href="route('portal.reports')">{{ __('customer.reports.title') }}</x-ui.button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('customer.dashboard.recent_transactions')" :padded="false">
                <livewire:customer.transaction-list :limit="5" />
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <livewire:customer.device-panel />

            <x-ui.card :title="__('customer.dashboard.notifications')">
                @forelse ($notifications as $notification)
                    <div class="border-b border-slate-100 py-2 text-sm last:border-0 {{ $notification->read_at ? 'text-slate-500' : 'font-medium text-slate-800' }}">
                        <p>{{ $notification->data['message'] ?? '' }}</p>
                        <p class="text-xs text-slate-400">{{ $notification->created_at?->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('customer.dashboard.no_notifications') }}</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</div>
