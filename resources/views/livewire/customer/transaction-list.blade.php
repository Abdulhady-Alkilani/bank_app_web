@php
    $statusColors = [
        'completed' => 'bg-emerald-100 text-emerald-800',
        'pending' => 'bg-slate-100 text-slate-700',
        'held' => 'bg-amber-100 text-amber-800',
        'blocked' => 'bg-red-100 text-red-800',
        'rejected' => 'bg-red-100 text-red-800',
    ];
@endphp
<div>
    @if ($transactions->isEmpty())
        <x-ui.empty-state :title="__('customer.activity.no_transactions')" class="border-0" />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('customer.activity.time') }}</th>
                <th>{{ __('customer.activity.beneficiary') }}</th>
                <th>{{ __('customer.activity.amount') }}</th>
                <th>{{ __('common.status') }}</th>
                <th>{{ __('customer.feedback.title') }}</th>
            </x-slot:head>
            @foreach ($transactions as $transaction)
                <tr wire:key="tx-{{ $transaction->id }}">
                    <td class="whitespace-nowrap text-slate-500">{{ $transaction->transaction_time?->format('Y-m-d H:i') }}</td>
                    <td>{{ $transaction->beneficiary?->beneficiary_name ?? '-' }}
                        <span class="block font-mono text-xs text-slate-400" dir="ltr">{{ $transaction->beneficiary?->beneficiary_account }}</span></td>
                    <td class="whitespace-nowrap font-semibold" dir="ltr">{{ number_format((float) $transaction->amount, 2) }}</td>
                    <td><x-ui.badge :color="$statusColors[$transaction->status->value] ?? null" :label="$transaction->status->label()" /></td>
                    <td>
                        @if ($answers->has($transaction->id))
                            <span class="text-xs {{ $answers[$transaction->id] ? 'text-red-600' : 'text-emerald-700' }}">
                                {{ $answers[$transaction->id] ? __('customer.feedback.denied') : __('customer.feedback.confirmed') }}
                            </span>
                        @else
                            <div class="flex flex-wrap gap-1">
                                <button type="button" wire:click="confirm({{ $transaction->id }})"
                                    class="rounded-md border border-emerald-300 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100">{{ __('customer.feedback.yes') }}</button>
                                <button type="button" wire:click="deny({{ $transaction->id }})"
                                    class="rounded-md border border-red-300 bg-red-50 px-2 py-1 text-xs font-medium text-red-800 hover:bg-red-100">{{ __('customer.feedback.no') }}</button>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</div>
