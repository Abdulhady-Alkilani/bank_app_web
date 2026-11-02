@php $field = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none'; @endphp
<div class="space-y-4">
    <x-ui.card>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.status') }}</label>
                <select wire:model.live="status" class="{{ $field }}">
                    <option value="">{{ __('analyst.queue.all') }}</option>
                    @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.priority') }}</label>
                <select wire:model.live="priority" class="{{ $field }}">
                    <option value="">{{ __('analyst.queue.all') }}</option>
                    @foreach ($priorities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.channel') }}</label>
                <select wire:model.live="channel" class="{{ $field }}">
                    <option value="">{{ __('analyst.queue.all') }}</option>
                    @foreach ($channels as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model.live="mine" class="rounded border-slate-300 text-brand-600"> {{ __('analyst.queue.mine') }}
                </label>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.min_score') }}</label>
                    <input type="number" min="0" max="100" wire:model.live.debounce.500ms="minScore" class="{{ $field }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.max_score') }}</label>
                    <input type="number" min="0" max="100" wire:model.live.debounce.500ms="maxScore" class="{{ $field }}">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.min_amount') }}</label>
                    <input type="number" min="0" wire:model.live.debounce.500ms="minAmount" class="{{ $field }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.max_amount') }}</label>
                    <input type="number" min="0" wire:model.live.debounce.500ms="maxAmount" class="{{ $field }}">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.date_from') }}</label>
                <input type="date" wire:model.live="dateFrom" class="{{ $field }}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.queue.date_to') }}</label>
                <input type="date" wire:model.live="dateTo" class="{{ $field }}">
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <x-ui.button variant="ghost" wire:click="resetFilters">{{ __('analyst.queue.reset') }}</x-ui.button>
        </div>
    </x-ui.card>

    @if ($alerts->isEmpty())
        <x-ui.empty-state :title="__('analyst.queue.empty')" />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>#</th>
                <th>{{ __('analyst.queue.score') }}</th>
                <th>{{ __('analyst.queue.priority') }}</th>
                <th>{{ __('analyst.queue.status') }}</th>
                <th>{{ __('analyst.queue.account') }}</th>
                <th>{{ __('analyst.queue.amount') }}</th>
                <th>{{ __('analyst.queue.channel') }}</th>
                <th>{{ __('analyst.queue.assignee') }}</th>
                <th>{{ __('analyst.queue.created') }}</th>
                <th></th>
            </x-slot:head>
            @foreach ($alerts as $alert)
                @php $tx = $alert->riskScore?->transaction; $account = $tx?->account ?? $alert->riskScore?->event?->account; @endphp
                <tr wire:key="alert-{{ $alert->id }}" class="hover:bg-slate-50">
                    <td class="font-mono text-xs text-slate-500">{{ $alert->id }}</td>
                    <td><x-analyst.score :score="$alert->riskScore?->score ?? 0" /></td>
                    <td><x-ui.badge :value="$alert->priority" /></td>
                    <td><x-ui.badge :value="$alert->status" /></td>
                    <td class="font-mono text-xs">{{ $account?->account_number ?? '—' }}</td>
                    <td class="tabular-nums">{{ $tx ? number_format((float) $tx->amount, 2) : '—' }}</td>
                    <td>{{ $tx?->channel ?? __('analyst.queue.event') }}</td>
                    <td>{{ $alert->assignee?->name ?? __('analyst.queue.unassigned') }}</td>
                    <td class="whitespace-nowrap text-xs text-slate-500">{{ $alert->created_at?->diffForHumans() }}</td>
                    <td class="whitespace-nowrap text-end">
                        @if ($alert->assigned_to === null && auth()->user()->can('decide', $alert))
                            <x-ui.button variant="ghost" wire:click="claim({{ $alert->id }})" class="!px-2 !py-1 text-xs">{{ __('analyst.queue.claim') }}</x-ui.button>
                        @endif
                        <x-ui.button variant="secondary" :href="route('analyst.alerts.show', $alert)" wire:navigate class="!px-3 !py-1 text-xs">{{ __('analyst.queue.open') }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div>{{ $alerts->links() }}</div>
    @endif
</div>
