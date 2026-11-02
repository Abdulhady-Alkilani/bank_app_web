<div class="space-y-4">
    <x-ui.card>
        <div class="flex flex-wrap items-end gap-3">
            <x-ui.input name="from" type="date" wire:model.live="from" :label="__('supervisor.reports.from')" />
            <x-ui.input name="to" type="date" wire:model.live="to" :label="__('supervisor.reports.to')" />
            @foreach ([7, 30, 90] as $d)
                <x-ui.button variant="ghost" wire:click="preset({{ $d }})">{{ __('supervisor.days', ['n' => $d]) }}</x-ui.button>
            @endforeach
            <div class="ms-auto flex gap-2">
                <x-ui.button wire:click="exportPdf">{{ __('supervisor.reports.export_pdf') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="exportExcel">{{ __('supervisor.reports.export_excel') }}</x-ui.button>
            </div>
        </div>
    </x-ui.card>

    @if ($summary)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat :label="__('supervisor.reports.alerts_total')" :value="$summary['alerts_total']" />
            <x-ui.stat :label="__('supervisor.reports.cases_total')" :value="$summary['cases_total']" />
            <x-ui.stat :label="__('supervisor.reports.avg_handling')" :value="$summary['avg_handling_minutes'] ?? '—'" :hint="__('supervisor.minutes')" />
            <x-ui.stat :label="__('supervisor.reports.blocked_amount')" :value="number_format($summary['blocked_amount'], 2)" tone="danger" />
        </div>

        @foreach ($rows as $section => $items)
            <x-ui.card :title="$section" :padded="false" wire:key="section-{{ $loop->index }}">
                <x-ui.table class="rounded-none border-0 shadow-none">
                    <x-slot:head><th>{{ __('supervisor.reports.metric') }}</th><th>{{ __('supervisor.reports.value') }}</th></x-slot:head>
                    @foreach ($items as $row)
                        <tr><td>{{ $row[1] }}</td><td>{{ $row[2] }}</td></tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>
        @endforeach
    @endif
</div>
