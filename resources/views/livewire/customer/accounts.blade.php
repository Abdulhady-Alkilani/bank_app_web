<div class="space-y-6">
    @include('livewire.customer.partials.frozen-banner', ['frozen' => $frozen])

    @forelse ($accounts as $account)
        <x-ui.card :title="__('customer.accounts.account').' '.$account->account_number">
            <x-slot:actions>
                <x-ui.badge :color="$account->isFrozen() ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'" :label="$account->status->label()" />
            </x-slot:actions>

            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-slate-500">{{ __('customer.accounts.balance') }}</dt>
                    <dd class="text-xl font-bold" dir="ltr">{{ number_format((float) $account->balance, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('customer.accounts.type') }}</dt>
                    <dd class="text-sm font-medium">{{ $account->type }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">{{ __('customer.accounts.limit') }}</dt>
                    <dd class="text-sm font-medium" dir="ltr">{{ number_format((float) ($account->transfer_limit ?? $defaultLimit), 2) }}</dd>
                </div>
            </dl>

            <h3 class="mb-2 mt-5 text-sm font-semibold text-slate-700">{{ __('customer.accounts.beneficiaries') }}</h3>
            @forelse ($account->beneficiaries as $beneficiary)
                <div class="flex items-center justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                    <span>{{ $beneficiary->beneficiary_name }} <span class="font-mono text-xs text-slate-400" dir="ltr">{{ $beneficiary->beneficiary_account }}</span></span>
                    <span class="text-xs text-slate-500">{{ $beneficiary->bank_name }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('customer.accounts.no_beneficiaries') }}</p>
            @endforelse
        </x-ui.card>
    @empty
        <x-ui.empty-state :title="__('customer.accounts.none')" />
    @endforelse
</div>
