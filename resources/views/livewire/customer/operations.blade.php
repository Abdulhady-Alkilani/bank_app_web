@php
    $tabs = ['transfer' => 'tab_transfer', 'beneficiary' => 'tab_beneficiary', 'phone' => 'tab_phone', 'limit' => 'tab_limit'];
    $resultColors = [
        'allow' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'challenge' => 'border-amber-200 bg-amber-50 text-amber-800',
        'block' => 'border-red-200 bg-red-50 text-red-800',
        'frozen' => 'border-red-200 bg-red-50 text-red-800',
    ];
@endphp
<div class="space-y-6">
    @if ($account?->isFrozen())
        @include('livewire.customer.partials.frozen-banner', ['frozen' => collect([$account])])
    @endif

    @if ($result)
        <div class="rounded-xl border px-4 py-3 {{ $resultColors[$result['status']] ?? $resultColors['block'] }}" role="alert" data-result="{{ $result['status'] }}">
            <p class="text-sm font-semibold">
                @if ($result['status'] === 'allow') &#10003; @endif
                {{ $result['message'] }}
            </p>
            <p class="mt-1 text-xs opacity-75">{{ __('customer.ops.sim_detail', ['score' => $result['score'], 'decision' => __('enums.Decision.'.$result['decision'])]) }}</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:click="setTab('{{ $key }}')"
                        @class([
                            'rounded-lg px-4 py-2 text-sm font-medium transition',
                            'bg-brand-600 text-white' => $tab === $key,
                            'text-slate-600 hover:bg-slate-100' => $tab !== $key,
                        ])>{{ __('customer.ops.'.$label) }}</button>
                @endforeach
            </div>

            <x-ui.card>
                @if ($accounts->count() > 1)
                    <div class="mb-4">
                        <x-console.select name="accountId" wire:model.live="accountId" :label="__('customer.ops.from_account')">
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->account_number }} ({{ number_format((float) $a->balance, 2) }})</option>
                            @endforeach
                        </x-console.select>
                    </div>
                @elseif ($account)
                    <p class="mb-4 text-sm text-slate-500">{{ __('customer.ops.from_account') }}: <span class="font-mono" dir="ltr">{{ $account->account_number }}</span>
                        &mdash; <span dir="ltr">{{ number_format((float) $account->balance, 2) }}</span></p>
                @endif

                @if ($tab === 'transfer')
                    <form wire:submit="submitTransfer" class="space-y-4">
                        <x-console.select name="beneficiaryId" wire:model.live="beneficiaryId" :label="__('customer.ops.beneficiary')">
                            <option value="new">{{ __('customer.ops.new_beneficiary') }}</option>
                            @foreach ($beneficiaries as $b)
                                <option value="{{ $b->id }}">{{ $b->beneficiary_name }} - {{ $b->beneficiary_account }}</option>
                            @endforeach
                        </x-console.select>

                        @if ($beneficiaryId === 'new')
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-ui.input name="beneficiaryAccount" wire:model="beneficiaryAccount" :label="__('customer.ops.beneficiary_account')" dir="ltr" />
                                <x-ui.input name="beneficiaryName" wire:model="beneficiaryName" :label="__('customer.ops.beneficiary_name')" />
                                <x-ui.input name="bankName" wire:model="bankName" :label="__('customer.ops.bank_name')" />
                            </div>
                        @endif

                        <x-ui.input name="amount" type="number" step="0.01" wire:model="amount" :label="__('customer.ops.amount')" dir="ltr" />
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('customer.ops.send') }}</x-ui.button>
                    </form>
                @elseif ($tab === 'beneficiary')
                    <form wire:submit="submitBeneficiary" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.input name="beneficiaryAccount" wire:model="beneficiaryAccount" :label="__('customer.ops.beneficiary_account')" dir="ltr" />
                            <x-ui.input name="beneficiaryName" wire:model="beneficiaryName" :label="__('customer.ops.beneficiary_name')" />
                            <x-ui.input name="bankName" wire:model="bankName" :label="__('customer.ops.bank_name')" />
                        </div>
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('customer.ops.add_beneficiary') }}</x-ui.button>
                    </form>
                @elseif ($tab === 'phone')
                    <form wire:submit="submitPhone" class="space-y-4">
                        <x-ui.input name="newPhone" wire:model="newPhone" :label="__('customer.ops.new_phone')" dir="ltr" />
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('customer.ops.change_phone') }}</x-ui.button>
                    </form>
                @else
                    <form wire:submit="submitLimit" class="space-y-4">
                        <x-ui.input name="newLimit" type="number" step="0.01" wire:model="newLimit" :label="__('customer.ops.new_limit')" dir="ltr" />
                        <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('customer.ops.raise_limit') }}</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </div>

        <div>
            <livewire:customer.device-panel />
        </div>
    </div>
</div>
