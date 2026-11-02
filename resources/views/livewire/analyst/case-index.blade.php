<div class="space-y-4">
    <x-ui.card>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.cases.status') }}</label>
                <select wire:model.live="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">{{ __('analyst.queue.all') }}</option>
                    @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                <input type="checkbox" wire:model.live="mine" class="rounded border-slate-300 text-brand-600"> {{ __('analyst.cases.mine') }}
            </label>
        </div>
    </x-ui.card>

    @if ($cases->isEmpty())
        <x-ui.empty-state :title="__('analyst.cases.empty')" :description="__('analyst.cases.empty_hint')" />
    @else
        <x-ui.table>
            <x-slot:head>
                <th>#</th><th>{{ __('analyst.cases.case_title') }}</th><th>{{ __('analyst.queue.priority') }}</th><th>{{ __('analyst.cases.status') }}</th>
                <th>{{ __('analyst.cases.alerts') }}</th><th>{{ __('analyst.cases.assignee') }}</th><th>{{ __('analyst.cases.opened_at') }}</th><th></th>
            </x-slot:head>
            @foreach ($cases as $case)
                <tr wire:key="case-{{ $case->id }}" class="hover:bg-slate-50">
                    <td class="font-mono text-xs text-slate-500">{{ $case->id }}</td>
                    <td class="font-medium">{{ $case->title }}</td>
                    <td><x-ui.badge :value="$case->priority" /></td>
                    <td><x-ui.badge :value="$case->status" /></td>
                    <td class="tabular-nums">{{ $case->alerts_count }}</td>
                    <td>{{ $case->assignee?->name ?? __('analyst.queue.unassigned') }}</td>
                    <td class="whitespace-nowrap text-xs text-slate-500">{{ $case->created_at?->diffForHumans() }}</td>
                    <td class="text-end"><x-ui.button variant="secondary" :href="route('analyst.cases.show', $case)" wire:navigate class="!px-3 !py-1 text-xs">{{ __('analyst.queue.open') }}</x-ui.button></td>
                </tr>
            @endforeach
        </x-ui.table>
        <div>{{ $cases->links() }}</div>
    @endif
</div>
