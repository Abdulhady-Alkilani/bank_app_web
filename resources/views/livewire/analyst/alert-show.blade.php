@php
    $value = (int) ($score?->score ?? 0);
    $gaugeColor = $value >= 75 ? '#dc2626' : ($value >= 40 ? '#d97706' : '#059669');
    $circumference = 2 * M_PI * 52;
    $field = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none';
    $tx = $score?->transaction;
@endphp
<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button variant="ghost" :href="route('analyst.alerts')" wire:navigate>&larr; {{ __('common.back') }}</x-ui.button>
        <h2 class="text-lg font-semibold">{{ __('analyst.alert.title', ['id' => $alert->id]) }}</h2>
        <x-ui.badge :value="$alert->priority" />
        <x-ui.badge :value="$alert->status" />
        @if ($alert->decision)
            <x-ui.badge :value="$alert->decision" />
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Score & automated decision --}}
            <x-ui.card :title="__('analyst.alert.risk')">
                <div class="flex flex-wrap items-center gap-6">
                    <div class="relative size-32 shrink-0">
                        <svg viewBox="0 0 120 120" class="size-32 -rotate-90">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#e2e8f0" stroke-width="10" />
                            <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $gaugeColor }}" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - min($value, 100) / 100) }}" />
                        </svg>
                        <div class="absolute inset-0 grid place-items-center text-center">
                            <div><p class="text-3xl font-bold tabular-nums" style="color: {{ $gaugeColor }}">{{ $value }}</p><p class="text-xs text-slate-400">/ 100</p></div>
                        </div>
                    </div>
                    <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-slate-500">{{ __('analyst.alert.system_decision') }}</dt><dd class="mt-0.5">@if ($score?->decision)<x-ui.badge :value="$score->decision" />@else — @endif</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('analyst.alert.rule_score') }}</dt><dd class="font-medium tabular-nums">{{ $score?->rule_score ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('analyst.alert.model_probability') }}</dt><dd class="font-medium tabular-nums">{{ $score?->model_probability !== null ? round((float) $score->model_probability * 100).'%' : '—' }}</dd></div>
                        <div>
                            <dt class="text-xs text-slate-500">{{ __('analyst.alert.model_used') }}</dt>
                            <dd class="font-medium" dir="ltr">
                                @if ($score?->modelRun && ! $score->used_fallback)
                                    {{ $score->modelRun->model?->model_name }} <span class="text-slate-500">{{ $score->modelRun->version_number }}</span>
                                @else
                                    <span dir="auto">{{ __('analyst.alert.rules_only') }}</span>
                                @endif
                            </dd>
                        </div>
                        @if ($score?->shadow_probability !== null)
                            <div>
                                <dt class="text-xs text-slate-500">{{ __('analyst.alert.shadow_probability') }}</dt>
                                <dd class="font-medium tabular-nums">{{ round((float) $score->shadow_probability * 100) }}%
                                    @if ($score->shadowModelRun)<span class="text-xs font-normal text-slate-500" dir="ltr">({{ $score->shadowModelRun->model?->model_name }} {{ $score->shadowModelRun->version_number }})</span>@endif
                                </dd>
                            </div>
                        @endif
                        <div><dt class="text-xs text-slate-500">{{ __('analyst.alert.latency') }}</dt><dd class="tabular-nums">{{ $score?->latency_ms ?? '—' }} ms</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('analyst.alert.fallback') }}</dt><dd>{{ $score?->used_fallback ? __('common.yes') : __('common.no') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('analyst.alert.assignee') }}</dt><dd>{{ $alert->assignee?->name ?? __('analyst.queue.unassigned') }}</dd></div>
                    </dl>
                </div>
                @if ($alert->decision)
                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm">
                        <p class="font-semibold">{{ __('analyst.alert.analyst_decision') }}: {{ $alert->decision->label() }}</p>
                        <p class="mt-1 text-slate-600">{{ $alert->decision_reason }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $alert->decider?->name }} · {{ $alert->decided_at?->format('Y-m-d H:i') }}</p>
                    </div>
                @endif
            </x-ui.card>

            {{-- Reasons --}}
            <x-ui.card :title="__('analyst.alert.reasons')">
                @if ($reasons === [])
                    <p class="text-sm text-slate-500">{{ __('analyst.alert.no_reasons') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($reasons as $reason)
                            <li class="flex items-start gap-3 rounded-lg border border-slate-100 p-3 text-sm">
                                <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-amber-100 text-xs font-bold text-amber-700">{{ $loop->iteration }}</span>
                                <span class="flex-1">{{ $reason['text'] }}</span>
                                @if ($reason['impact'] !== null)
                                    <x-ui.badge :label="'+'.$reason['impact']" color="bg-red-50 text-red-700" />
                                @endif
                                @if ($reason['source'])
                                    <x-ui.badge :label="$reason['source']" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            {{-- Features --}}
            <x-ui.card :title="__('analyst.alert.features')" :padded="false">
                @if ($features === [])
                    <p class="p-5 text-sm text-slate-500">{{ __('analyst.alert.no_features') }}</p>
                @else
                    <dl class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-y-0">
                        @foreach ($features as $feature)
                            <div class="flex items-center justify-between gap-4 border-slate-100 px-5 py-2.5 text-sm sm:border-b">
                                <dt class="text-slate-500" title="{{ $feature['key'] }}">{{ $feature['label'] }}</dt>
                                <dd class="font-medium tabular-nums">{{ $feature['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-ui.card>

            {{-- Timeline --}}
            <x-ui.card :title="__('analyst.alert.timeline')">
                @if ($timeline->isEmpty())
                    <p class="text-sm text-slate-500">{{ __('analyst.alert.no_timeline') }}</p>
                @else
                    <ol class="relative space-y-4 border-s border-slate-200 ps-5">
                        @foreach ($timeline as $item)
                            <li class="relative" wire:key="tl-{{ $loop->index }}">
                                <span @class(['absolute -start-[1.65rem] top-1 size-3 rounded-full border-2 border-white', 'bg-red-500' => $item['current'], 'bg-slate-300' => ! $item['current']])></span>
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="font-medium">{{ $item['title'] }}</span>
                                    @if ($item['status'])<x-ui.badge :label="$item['status']" />@endif
                                    @if ($item['score'] !== null)<x-analyst.score :score="$item['score']" />@endif
                                    @if ($item['current'])<x-ui.badge :label="__('analyst.alert.flagged')" color="bg-red-100 text-red-800" />@endif
                                </div>
                                <p class="text-xs text-slate-500">{{ $item['time']->format('Y-m-d H:i:s') }} · {{ $item['detail'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-6">
            {{-- Subject --}}
            <x-ui.card :title="__('analyst.alert.subject')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.queue.account') }}</dt><dd class="font-mono">{{ $account?->account_number ?? '—' }}</dd></div>
                    @if ($account)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.alert.account_status') }}</dt><dd><x-ui.badge :value="$account->status" /></dd></div>
                    @endif
                    @if ($tx)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.queue.amount') }}</dt><dd class="font-semibold tabular-nums">{{ number_format((float) $tx->amount, 2) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.queue.channel') }}</dt><dd>{{ $tx->channel }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.alert.tx_status') }}</dt><dd><x-ui.badge :value="$tx->status" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">IP</dt><dd class="font-mono text-xs">{{ $tx->ip_address ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.customer.city') }}</dt><dd>{{ $tx->city ?? '—' }}</dd></div>
                        @if ($tx->beneficiary)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.alert.beneficiary') }}</dt><dd class="font-mono text-xs">{{ $tx->beneficiary->beneficiary_account }} @if ($tx->beneficiary->is_frozen)<x-ui.badge :label="__('analyst.alert.frozen')" color="bg-red-100 text-red-800" />@endif</dd></div>
                        @endif
                    @elseif ($score?->event)
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ __('analyst.alert.event') }}</dt><dd>{{ $score->event->event_type->label() }}</dd></div>
                    @endif
                </dl>
                @if ($account)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($account->customer)
                            <x-ui.button variant="secondary" :href="route('analyst.customers.show', ['customer' => $account->customer, 'alert' => $alert->id])" wire:navigate class="!px-3 !py-1.5 text-xs">{{ __('analyst.alert.customer_profile') }}</x-ui.button>
                        @endif
                        <x-ui.button variant="secondary" :href="route('analyst.accounts.network', $account)" wire:navigate class="!px-3 !py-1.5 text-xs">{{ __('analyst.alert.network') }}</x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            {{-- Decision --}}
            @if ($canDecide)
                <x-ui.card :title="__('analyst.alert.decide')">
                    <label for="reason" class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.alert.decision_reason') }}</label>
                    <textarea id="reason" wire:model="reason" rows="3" class="{{ $field }}" placeholder="{{ __('analyst.alert.reason_placeholder') }}"></textarea>
                    @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <x-ui.button variant="danger" wire:click="decide('confirmed_fraud')" wire:loading.attr="disabled">{{ __('analyst.decisions.confirmed_fraud') }}</x-ui.button>
                        <x-ui.button wire:click="decide('false_positive')" wire:loading.attr="disabled" class="!bg-emerald-600 hover:!bg-emerald-700">{{ __('analyst.decisions.false_positive') }}</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="decide('escalated')" wire:loading.attr="disabled">{{ __('analyst.decisions.escalated') }}</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="decide('info_requested')" wire:loading.attr="disabled">{{ __('analyst.decisions.info_requested') }}</x-ui.button>
                    </div>
                </x-ui.card>

                {{-- Actions --}}
                <x-ui.card :title="__('analyst.alert.actions')">
                    <label for="actionReason" class="mb-1 block text-xs font-medium text-slate-600">{{ __('analyst.alert.action_reason') }}</label>
                    <textarea id="actionReason" wire:model="actionReason" rows="2" class="{{ $field }}"></textarea>
                    @error('actionReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        @if ($tx)
                            <x-ui.button variant="secondary" wire:click="performAction('block_transaction')">{{ __('analyst.actions.block_transaction') }}</x-ui.button>
                        @endif
                        <x-ui.button variant="secondary" wire:click="performAction('require_otp')">{{ __('analyst.actions.require_otp') }}</x-ui.button>
                        @if ($canFreeze)
                            <x-ui.button variant="secondary" wire:click="performAction('freeze_account')">{{ __('analyst.actions.freeze_account') }}</x-ui.button>
                            @if ($tx?->beneficiary)
                                <x-ui.button variant="secondary" wire:click="performAction('freeze_beneficiary')">{{ __('analyst.actions.freeze_beneficiary') }}</x-ui.button>
                            @endif
                        @endif
                        @if ($canUnfreeze && $account?->isFrozen())
                            <x-ui.button variant="secondary" wire:click="performAction('unfreeze_account')">{{ __('analyst.actions.unfreeze_account') }}</x-ui.button>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-slate-400">{{ __('analyst.alert.four_eyes_hint') }}</p>

                    @if ($pending->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            <p class="text-xs font-semibold text-amber-700">{{ __('analyst.alert.pending_actions') }}</p>
                            @foreach ($pending as $action)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" wire:key="pa-{{ $action->id }}">
                                    {{ $action->action->label() }} · {{ __('analyst.alert.awaiting_approval') }} · {{ $action->requester?->name }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endif

            {{-- Case --}}
            <x-ui.card :title="__('analyst.alert.case')">
                @if ($alert->case)
                    <p class="text-sm"><a class="font-medium text-brand-700 hover:underline" href="{{ route('analyst.cases.show', $alert->case) }}" wire:navigate>#{{ $alert->case->id }} · {{ $alert->case->title }}</a></p>
                    <div class="mt-1"><x-ui.badge :value="$alert->case->status" /></div>
                @elseif ($canDecide)
                    <p class="mb-3 text-sm text-slate-500">{{ __('analyst.alert.no_case') }}</p>
                    <x-ui.button variant="secondary" wire:click="openCase">{{ __('analyst.alert.open_case') }}</x-ui.button>
                @else
                    <p class="text-sm text-slate-500">{{ __('analyst.alert.no_case') }}</p>
                @endif
            </x-ui.card>
        </div>
    </div>
</div>
