<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('analyst.dashboard.my_open_alerts')" :value="$myOpenAlerts" />
        <x-ui.stat :label="__('analyst.dashboard.critical_alerts')" :value="$criticalAlerts" tone="danger" />
        <x-ui.stat :label="__('analyst.dashboard.my_cases')" :value="$myCases" />
        <x-ui.stat :label="__('analyst.dashboard.unassigned')" :value="$unassigned" tone="warning" />
    </div>

    <x-ui.card :title="__('analyst.dashboard.recent_high_risk')" :padded="false">
        <x-slot:actions>
            <x-ui.button variant="ghost" :href="route('analyst.alerts')" wire:navigate>{{ __('analyst.dashboard.view_all') }}</x-ui.button>
        </x-slot:actions>
        @if ($recent->isEmpty())
            <x-ui.empty-state :title="__('analyst.dashboard.no_high_risk')" class="border-0" />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($recent as $alert)
                    @php $tx = $alert->riskScore?->transaction; $account = $tx?->account ?? $alert->riskScore?->event?->account; @endphp
                    <li>
                        <a href="{{ route('analyst.alerts.show', $alert) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                            <x-analyst.score :score="$alert->riskScore?->score ?? 0" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">#{{ $alert->id }} · {{ $account?->account_number ?? '—' }}</span>
                                <span class="block text-xs text-slate-500">{{ $alert->created_at?->diffForHumans() }}@if ($tx) · {{ number_format((float) $tx->amount, 2) }}@endif</span>
                            </span>
                            <x-ui.badge :value="$alert->priority" />
                            <x-ui.badge :value="$alert->status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
