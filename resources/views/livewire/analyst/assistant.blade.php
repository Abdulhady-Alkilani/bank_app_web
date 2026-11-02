<div class="space-y-6">
    <x-ui.card :title="__('analyst.assistant.connect_title')">
        <p class="text-sm text-slate-600">{{ __('analyst.assistant.connect_intro') }}</p>
        <dl class="mt-4 space-y-3 text-sm">
            <div>
                <dt class="text-xs font-semibold text-slate-500">{{ __('analyst.assistant.server_url') }}</dt>
                <dd class="mt-1"><code class="block rounded-lg bg-slate-900 px-3 py-2 text-xs text-emerald-300" dir="ltr">{{ $mcpUrl }}</code></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-slate-500">{{ __('analyst.assistant.auth') }}</dt>
                <dd class="mt-1 text-slate-600">{{ __('analyst.assistant.auth_help') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-slate-500">{{ __('analyst.assistant.config_example') }}</dt>
                <dd class="mt-1"><pre class="overflow-x-auto rounded-lg bg-slate-900 px-3 py-2 text-xs text-slate-100" dir="ltr">{
  "mcpServers": {
    "fraud": {
      "url": "{{ $mcpUrl }}",
      "headers": { "Authorization": "Bearer &lt;YOUR_MCP_TOKEN&gt;" }
    }
  }
}</pre></dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-slate-400">{{ __('analyst.assistant.safety_note') }}</p>
    </x-ui.card>

    @if ($error)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $error }}</div>
    @endif

    <x-ui.card :title="__('analyst.assistant.pending')" :padded="false">
        @if ($pending->isEmpty())
            <p class="p-5 text-sm text-slate-500">{{ __('analyst.assistant.no_pending') }}</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($pending as $proposal)
                    <li class="space-y-2 px-5 py-4" wire:key="prop-{{ $proposal->id }}">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <x-ui.badge :label="$proposal->tool_name" color="bg-violet-100 text-violet-800" />
                            @if ($proposal->case)<a class="text-brand-700 hover:underline" href="{{ route('analyst.cases.show', $proposal->case) }}" wire:navigate>{{ __('analyst.assistant.case', ['id' => $proposal->case->id]) }}</a>@endif
                            @if ($proposal->alert)<a class="text-brand-700 hover:underline" href="{{ route('analyst.alerts.show', $proposal->alert) }}" wire:navigate>{{ __('analyst.search.alert_link', ['id' => $proposal->alert->id]) }}</a>@endif
                            <span class="text-xs text-slate-400">{{ $proposal->created_at?->diffForHumans() }}</span>
                        </div>
                        <pre class="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-xs" dir="auto">{{ is_string($proposal->payload['note'] ?? null) ? $proposal->payload['note'] : json_encode($proposal->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        <div class="flex gap-2">
                            <x-ui.button wire:click="approve({{ $proposal->id }})" wire:loading.attr="disabled">{{ __('analyst.assistant.approve') }}</x-ui.button>
                            <x-ui.button variant="secondary" wire:click="reject({{ $proposal->id }})" wire:loading.attr="disabled">{{ __('analyst.assistant.reject') }}</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    @if ($history->isNotEmpty())
        <x-ui.card :title="__('analyst.assistant.history')" :padded="false">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($history as $proposal)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-2.5" wire:key="hist-{{ $proposal->id }}">
                        <x-ui.badge :label="$proposal->tool_name" />
                        <x-ui.badge :value="$proposal->status" />
                        <span class="text-xs text-slate-400">{{ $proposal->reviewer?->name }} · {{ $proposal->reviewed_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
