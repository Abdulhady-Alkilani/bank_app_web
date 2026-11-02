<div class="space-y-4">
    <x-console.flash :message="$flash" :error="$errorMessage" />

    <x-ui.card :title="__('supervisor.assignments.capacity')">
        @if ($analysts->isEmpty())
            <p class="text-sm text-slate-500">{{ __('supervisor.assignments.no_analysts') }}</p>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($analysts as $a)
                    @php $pct = min(100, (int) round($a['load'] / $capacity * 100)); @endphp
                    <li wire:key="cap-{{ $a['id'] }}">
                        <div class="flex justify-between text-sm"><span>{{ $a['name'] }}</span><span class="text-slate-500" dir="ltr">{{ $a['load'] }} / {{ $capacity }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100">
                            <div class="h-2 rounded-full {{ $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="flex flex-wrap items-center gap-3">
        <x-console.select name="kind" wire:model.live="kind" class="!w-auto">
            <option value="alerts">{{ __('supervisor.assignments.alerts') }}</option>
            <option value="cases">{{ __('supervisor.assignments.cases') }}</option>
        </x-console.select>
        <x-console.select name="scope" wire:model.live="scope" class="!w-auto">
            <option value="unassigned">{{ __('supervisor.assignments.unassigned') }}</option>
            <option value="assigned">{{ __('supervisor.assignments.assigned') }}</option>
        </x-console.select>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <x-console.select name="target" wire:model="target" class="!w-auto">
                <option value="">{{ __('supervisor.assignments.choose_analyst') }}</option>
                <option value="0">{{ __('supervisor.assignments.unassign') }}</option>
                @foreach ($analysts as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }}</option>@endforeach
            </x-console.select>
            <x-ui.button wire:click="assign">{{ __('supervisor.assignments.assign_selected') }}</x-ui.button>
        </div>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th class="w-8"></th><th>#</th>
            @if ($kind === 'alerts')
                <th>{{ __('supervisor.assignments.priority') }}</th><th>{{ __('common.status') }}</th><th>{{ __('supervisor.assignments.score') }}</th>
            @else
                <th>{{ __('supervisor.assignments.case_title') }}</th><th>{{ __('supervisor.assignments.priority') }}</th><th>{{ __('common.status') }}</th>
            @endif
            <th>{{ __('supervisor.assignments.assignee') }}</th>
        </x-slot:head>
        @forelse ($items as $item)
            <tr wire:key="{{ $kind }}-{{ $item->id }}">
                <td><input type="checkbox" wire:model="selected" value="{{ $item->id }}" class="rounded border-slate-300" aria-label="{{ $item->id }}"></td>
                <td>{{ $item->id }}</td>
                @if ($kind === 'alerts')
                    <td><x-ui.badge :value="$item->priority" /></td>
                    <td>{{ $item->status->label() }}</td>
                    <td>{{ $item->riskScore?->score ?? '—' }}</td>
                @else
                    <td>{{ $item->title }}</td>
                    <td><x-ui.badge :value="$item->priority" /></td>
                    <td>{{ $item->status->label() }}</td>
                @endif
                <td>{{ $item->assignee?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state /></td></tr>
        @endforelse
    </x-ui.table>

    {{ $items->links() }}
</div>
