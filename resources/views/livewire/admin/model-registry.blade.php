<div class="space-y-6">
    <x-console.flash :message="$flash" />

    <p class="text-sm text-slate-500">{{ __('admin.models.hint') }}</p>

    <x-ui.card :title="__('admin.models.registry')" :padded="false">
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-slot:head>
                <th>{{ __('admin.models.name') }}</th><th>{{ __('admin.models.algorithm') }}</th><th>{{ __('common.status') }}</th>
                <th>{{ __('admin.models.latest_run') }}</th><th>{{ __('admin.models.final_score') }}</th>
                @if ($canPromote)<th>{{ __('common.actions') }}</th>@endif
            </x-slot:head>
            @forelse ($models as $model)
                @php
                    $latest = $model->runs->first();
                    $color = match ($model->status->value) {
                        'champion' => 'bg-emerald-100 text-emerald-800',
                        'shadow' => 'bg-sky-100 text-sky-800',
                        'candidate' => 'bg-amber-100 text-amber-800',
                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp
                <tr wire:key="model-{{ $model->id }}">
                    <td class="font-medium">{{ $model->model_name }}
                        @if ($latest && \App\Support\ModelScore::excludedFromRealtime($latest->metrics))
                            <x-ui.badge color="bg-red-100 text-red-800" :label="__('admin.models.excluded_realtime')" />
                        @endif
                    </td>
                    <td>{{ $model->algorithm }}</td>
                    <td><x-ui.badge :color="$color" :label="$model->status->label()" /></td>
                    <td dir="ltr" class="text-start">{{ $latest ? $latest->version_number : '—' }}</td>
                    <td>{{ $latest ? (\App\Support\ModelScore::final($latest->metrics) ?? '—') : '—' }}</td>
                    @if ($canPromote)
                        <td>
                            <div class="flex flex-wrap gap-2">
                                @if ($model->status !== \App\Enums\ModelStatus::Champion)
                                    <x-ui.button wire:click="promote({{ $model->id }})" wire:confirm="{{ __('admin.models.promote_confirm') }}">{{ __('admin.models.promote') }}</x-ui.button>
                                    @if ($model->status !== \App\Enums\ModelStatus::Shadow)
                                        <x-ui.button variant="secondary" wire:click="setStatus({{ $model->id }}, 'shadow')">{{ __('admin.models.set_shadow') }}</x-ui.button>
                                    @endif
                                    @if ($model->status !== \App\Enums\ModelStatus::Archived)
                                        <x-ui.button variant="ghost" wire:click="setStatus({{ $model->id }}, 'archived')">{{ __('admin.models.archive') }}</x-ui.button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    @if ($models->isNotEmpty())
        <x-ui.card :title="__('admin.models.comparison')">
            <div wire:ignore wire:key="chart-{{ md5(json_encode($chart)) }}" dir="ltr"
                 x-data="{ chart: null }"
                 x-init="
                    const data = @js($chart);
                    if (window.ApexCharts) {
                        chart = new window.ApexCharts($refs.canvas, {
                            chart: { type: 'bar', height: 340, toolbar: { show: false }, fontFamily: 'inherit' },
                            series: data.series,
                            xaxis: { categories: data.labels },
                            yaxis: { max: 100, min: 0, title: { text: '%' } },
                            plotOptions: { bar: { columnWidth: '60%' } },
                            dataLabels: { enabled: false },
                            legend: { position: 'bottom' },
                        });
                        chart.render();
                    }
                 ">
                <div x-ref="canvas"></div>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card :title="__('admin.models.drift_history')" :padded="false">
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-slot:head>
                <th>{{ __('admin.models.name') }}</th><th>{{ __('admin.models.version') }}</th>
                <th>{{ __('admin.models.drift') }}</th><th>{{ __('admin.models.psi_max') }}</th><th>{{ __('admin.models.trained_at') }}</th>
            </x-slot:head>
            @forelse ($drift as $row)
                <tr wire:key="drift-{{ $loop->index }}">
                    <td>{{ $row['model'] }}</td>
                    <td dir="ltr" class="text-start">{{ $row['version'] }}</td>
                    <td><x-ui.badge color="bg-red-100 text-red-800" :label="__('admin.models.drift_detected')" /></td>
                    <td class="tabular-nums">{{ $row['psi_max'] ?? '—' }}</td>
                    <td dir="ltr" class="text-start">{{ $row['date']?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-4 text-sm text-slate-500">{{ __('admin.models.no_drift') }}</td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <x-ui.card :title="__('admin.models.runs')" :padded="false">
        <p class="px-5 pt-3 text-xs text-slate-400">{{ __('admin.models.weights_note', ['weights' => collect($weights)->map(fn ($w, $m) => __('admin.models.metrics.'.$m).' '.$w.'%')->implode(' / ')]) }}</p>
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-slot:head>
                <th>{{ __('admin.models.name') }}</th><th>{{ __('admin.models.version') }}</th>
                @foreach ($metricKeys as $key)<th>{{ __('admin.models.metrics.'.$key) }}</th>@endforeach
                <th>{{ __('admin.models.final_score') }}</th><th>{{ __('admin.models.trained_at') }}</th>
            </x-slot:head>
            @forelse ($runs as $row)
                <tr wire:key="run-{{ $row['run']->id }}">
                    <td>{{ $row['run']->model?->model_name }}
                        @if ($row['excluded'])
                            <x-ui.badge color="bg-red-100 text-red-800" :label="__('admin.models.excluded_realtime')" />
                        @endif
                    </td>
                    <td dir="ltr" class="text-start">{{ $row['run']->version_number }}</td>
                    @foreach ($metricKeys as $key)
                        <td>{{ $row['metrics'][$key] === null ? '—' : $row['metrics'][$key] }}</td>
                    @endforeach
                    <td class="font-semibold">{{ $row['final'] ?? '—' }}</td>
                    <td dir="ltr" class="text-start">{{ $row['run']->trained_at?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($metricKeys) + 4 }}"><x-ui.empty-state /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
