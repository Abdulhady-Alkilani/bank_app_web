@php
    $reportColors = ['new' => 'bg-amber-100 text-amber-800', 'reviewing' => 'bg-sky-100 text-sky-800', 'resolved' => 'bg-emerald-100 text-emerald-800'];
@endphp
<div class="space-y-6">
    @if ($submitted)
        <x-console.flash :message="__('customer.reports.submitted')" />
    @endif

    <x-ui.card :title="__('customer.reports.form_title')">
        <p class="mb-4 text-sm text-slate-500">{{ __('customer.reports.intro') }}</p>
        <form wire:submit="submit" class="space-y-4">
            <x-console.select name="transactionId" wire:model="transactionId" :label="__('customer.reports.transaction')">
                <option value="">{{ __('customer.reports.choose') }}</option>
                @foreach ($transactions as $transaction)
                    <option value="{{ $transaction->id }}">
                        {{ $transaction->transaction_time?->format('Y-m-d H:i') }} - {{ number_format((float) $transaction->amount, 2) }}
                        {{ $transaction->beneficiary ? '→ '.$transaction->beneficiary->beneficiary_name : '' }}
                    </option>
                @endforeach
            </x-console.select>
            <x-console.textarea name="description" wire:model="description" rows="3" :label="__('customer.reports.description')" />
            <x-ui.button type="submit">{{ __('customer.reports.submit') }}</x-ui.button>
        </form>
    </x-ui.card>

    <div>
        <h2 class="mb-2 text-sm font-semibold text-slate-700">{{ __('customer.reports.my_reports') }}</h2>
        @if ($reports->isEmpty())
            <x-ui.empty-state :title="__('customer.reports.none')" />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>{{ __('customer.activity.time') }}</th>
                    <th>{{ __('customer.activity.amount') }}</th>
                    <th>{{ __('customer.reports.description') }}</th>
                    <th>{{ __('common.status') }}</th>
                </x-slot:head>
                @foreach ($reports as $report)
                    <tr wire:key="report-{{ $report->id }}">
                        <td class="whitespace-nowrap text-slate-500">{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                        <td dir="ltr">{{ $report->transaction ? number_format((float) $report->transaction->amount, 2) : '-' }}</td>
                        <td class="max-w-md truncate">{{ $report->description }}</td>
                        <td><x-ui.badge :color="$reportColors[$report->status->value] ?? null" :label="$report->status->label()" /></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </div>
</div>
