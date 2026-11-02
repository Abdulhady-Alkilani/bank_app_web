<div class="space-y-6" wire:poll.60s>
    <div class="flex items-center gap-3">
        <label for="days" class="text-sm text-slate-500">{{ __('supervisor.period') }}</label>
        <x-console.select name="days" wire:model.live="days" class="!w-auto">
            @foreach ($periods as $p)<option value="{{ $p }}">{{ __('supervisor.days', ['n' => $p]) }}</option>@endforeach
        </x-console.select>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('supervisor.dashboard.alerts')" :value="$s['alerts_total']" />
        <x-ui.stat :label="__('supervisor.dashboard.avg_handling')" :value="$s['avg_handling_minutes'] === null ? '—' : $s['avg_handling_minutes'].' '.__('supervisor.minutes')" />
        <x-ui.stat :label="__('supervisor.dashboard.false_positive_rate')" :value="$s['false_positive_rate'] === null ? '—' : $s['false_positive_rate'].'%'" />
        <x-ui.stat :label="__('supervisor.dashboard.blocked_amount')" :value="number_format($s['blocked_amount'], 2)" :hint="__('supervisor.dashboard.blocked_count', ['n' => $s['blocked_count']])" tone="danger" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('supervisor.dashboard.alerts_per_day')" class="lg:col-span-2">
            <x-console.chart :options="$charts['perDay']" />
        </x-ui.card>
        <x-ui.card :title="__('supervisor.dashboard.workload')">
            @if ($s['workload'] === [])
                <p class="text-sm text-slate-500">{{ __('supervisor.dashboard.no_workload') }}</p>
            @else
                <x-console.chart :options="$charts['workload']" />
            @endif
        </x-ui.card>
        <x-ui.card :title="__('supervisor.dashboard.decisions')">
            @if ($s['decided_total'] === 0)
                <p class="text-sm text-slate-500">{{ __('supervisor.dashboard.no_decisions') }}</p>
            @else
                <x-console.chart :options="$charts['decisions']" />
            @endif
        </x-ui.card>
    </div>
</div>
