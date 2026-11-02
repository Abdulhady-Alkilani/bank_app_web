<div class="space-y-6" wire:poll.30s>
    <x-console.flash :message="$flash" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('admin.health.ai_service')" :value="__('admin.health.ai.'.$aiStatus)"
                   :tone="match ($aiStatus) { 'up' => 'success', 'down' => 'danger', default => 'warning' }" />
        <x-ui.stat :label="__('admin.health.avg_latency')" :value="$stats['avg'] === null ? '—' : $stats['avg'].' ms'" :hint="__('admin.health.last_24h')" />
        <x-ui.stat :label="__('admin.health.p95_latency')" :value="$stats['p95'] === null ? '—' : $stats['p95'].' ms'" :hint="__('admin.health.last_24h')" />
        <x-ui.stat :label="__('admin.health.fallback_rate')" :value="$stats['fallback_rate'] === null ? '—' : $stats['fallback_rate'].'%'"
                   :hint="__('admin.health.scored', ['count' => $stats['count']])" :tone="($stats['fallback_rate'] ?? 0) > 20 ? 'warning' : 'default'" />
    </div>

    <x-ui.card :title="__('admin.health.queues')">
        @if ($queues === [])
            <p class="text-sm text-slate-500">{{ __('admin.health.queues_empty') }}</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($queues as $queue => $size)
                    <li class="flex justify-between py-2"><span class="font-mono" dir="ltr">{{ $queue }}</span><span class="font-semibold">{{ $size }}</span></li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('admin.health.failed_jobs')" :padded="false">
        @if ($failedJobs === [])
            <p class="p-5 text-sm text-slate-500">{{ __('admin.health.no_failed') }}</p>
        @else
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-slot:head>
                    <th>{{ __('admin.health.queue') }}</th><th>{{ __('admin.health.exception') }}</th><th>{{ __('admin.health.failed_at') }}</th><th>{{ __('common.actions') }}</th>
                </x-slot:head>
                @foreach ($failedJobs as $job)
                    <tr wire:key="failed-{{ $job->id }}">
                        <td class="font-mono" dir="ltr">{{ $job->queue }}</td>
                        <td class="max-w-md truncate" dir="ltr" title="{{ $job->exception }}">{{ \Illuminate\Support\Str::limit(strtok($job->exception, "\n"), 140) }}</td>
                        <td dir="ltr">{{ $job->failed_at }}</td>
                        <td class="flex gap-2">
                            <x-ui.button variant="secondary" wire:click="retry('{{ $job->uuid }}')">{{ __('admin.health.retry') }}</x-ui.button>
                            <x-ui.button variant="danger" wire:click="forget('{{ $job->uuid }}')" wire:confirm="{{ __('admin.health.forget_confirm') }}">{{ __('admin.health.forget') }}</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('admin.health.recent_errors')">
        @if ($errorLines === [])
            <p class="text-sm text-slate-500">{{ __('admin.health.no_errors') }}</p>
        @else
            <pre class="max-h-96 overflow-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-slate-100" dir="ltr">{{ implode("\n", $errorLines) }}</pre>
        @endif
    </x-ui.card>
</div>
