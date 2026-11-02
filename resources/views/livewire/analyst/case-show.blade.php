@php
    $field = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none';
@endphp
<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button variant="ghost" :href="route('analyst.cases')" wire:navigate>&larr; {{ __('common.back') }}</x-ui.button>
        <h2 class="text-lg font-semibold">#{{ $case->id }} · {{ $case->title }}</h2>
        <x-ui.badge :value="$case->priority" />
        <x-ui.badge :value="$case->status" />
        <div class="ms-auto flex gap-2">
            @if ($case->assigned_to === null)
                <x-ui.button variant="secondary" wire:click="claim">{{ __('analyst.cases.claim') }}</x-ui.button>
            @endif
            <x-ui.button variant="secondary" :href="route('analyst.cases.pdf', $case)" target="_blank">{{ __('analyst.cases.export_pdf') }}</x-ui.button>
        </div>
    </div>

    @if ($case->status === \App\Enums\CaseStatus::PendingApproval)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('analyst.cases.pending_approval_notice') }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('analyst.cases.total_amount')" :value="number_format($total, 2)" />
        <x-ui.stat :label="__('analyst.cases.alerts')" :value="$case->alerts->count()" />
        <x-ui.stat :label="__('analyst.cases.assignee')" :value="$case->assignee?->name ?? '—'" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Alerts --}}
            <x-ui.card :title="__('analyst.cases.alerts')" :padded="false">
                @if ($case->alerts->isEmpty())
                    <p class="p-5 text-sm text-slate-500">{{ __('analyst.cases.no_alerts') }}</p>
                @else
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($case->alerts as $alert)
                            @php $tx = $alert->riskScore?->transaction; @endphp
                            <li wire:key="ca-{{ $alert->id }}">
                                <a href="{{ route('analyst.alerts.show', $alert) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                                    <x-analyst.score :score="$alert->riskScore?->score ?? 0" />
                                    <span class="flex-1">#{{ $alert->id }} · <span class="font-mono text-xs">{{ $insights->account($alert)?->account_number ?? '—' }}</span>@if ($tx) · {{ number_format((float) $tx->amount, 2) }}@endif</span>
                                    <x-ui.badge :value="$alert->status" />
                                    @if ($alert->decision)<x-ui.badge :value="$alert->decision" />@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            {{-- Notes --}}
            <x-ui.card :title="__('analyst.cases.notes')">
                <form wire:submit="addNote" class="mb-4 space-y-2">
                    <textarea wire:model="note" rows="3" class="{{ $field }}" placeholder="{{ __('analyst.cases.note_placeholder') }}"></textarea>
                    @error('note')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <x-ui.button type="submit">{{ __('analyst.cases.add_note') }}</x-ui.button>
                </form>
                <ul class="space-y-3">
                    @forelse ($case->notes->sortByDesc('id') as $n)
                        <li class="rounded-lg border border-slate-100 p-3 text-sm" wire:key="note-{{ $n->id }}">
                            <div class="mb-1 flex items-center gap-2 text-xs text-slate-500">
                                <span class="font-semibold text-slate-700">{{ $n->user?->name ?? '—' }}</span>
                                @if ($n->is_ai_generated)<x-ui.badge :label="__('analyst.cases.ai_note')" color="bg-violet-100 text-violet-800" />@endif
                                <span>{{ $n->created_at?->format('Y-m-d H:i') }}</span>
                            </div>
                            <p class="whitespace-pre-line">{{ $n->note }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">{{ __('analyst.cases.no_notes') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>

            {{-- Attachments --}}
            <x-ui.card :title="__('analyst.cases.attachments')">
                <form wire:submit="uploadAttachment" class="mb-4 flex flex-wrap items-center gap-3">
                    <input type="file" wire:model="attachment" class="text-sm file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm">
                    <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="attachment">{{ __('analyst.cases.upload') }}</x-ui.button>
                    <span wire:loading wire:target="attachment" class="text-xs text-slate-500">…</span>
                </form>
                @error('attachment')<p class="mb-2 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mb-3 text-xs text-slate-400">{{ __('analyst.cases.upload_hint') }}</p>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($case->attachments as $file)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="att-{{ $file->id }}">
                            <a href="{{ route('analyst.cases.attachments.download', $file) }}" class="font-medium text-brand-700 hover:underline">{{ $file->file_name }}</a>
                            <span class="text-xs text-slate-400">{{ number_format($file->file_size / 1024, 1) }} KB · {{ $file->uploader?->name }}</span>
                            <button type="button" wire:click="deleteAttachment({{ $file->id }})" wire:confirm="{{ __('analyst.cases.delete_confirm') }}" class="text-xs text-red-600 hover:underline">{{ __('analyst.cases.delete') }}</button>
                        </li>
                    @empty
                        <li class="py-2 text-slate-500">{{ __('analyst.cases.no_attachments') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @unless ($closed)
                <x-ui.card :title="__('analyst.cases.change_status')">
                    <div class="flex gap-2">
                        <select wire:model="newStatus" class="{{ $field }}">
                            @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                        <x-ui.button variant="secondary" wire:click="changeStatus">{{ __('common.save') }}</x-ui.button>
                    </div>
                    @error('newStatus')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </x-ui.card>

                <x-ui.card :title="__('analyst.cases.merge')">
                    @if ($related->isNotEmpty())
                        <p class="mb-2 text-xs text-slate-500">{{ __('analyst.cases.related_hint') }}</p>
                        <ul class="mb-3 space-y-1.5 text-sm">
                            @foreach ($related as $r)
                                <li class="flex items-center gap-2" wire:key="rel-{{ $r->id }}">
                                    <x-analyst.score :score="$r->riskScore?->score ?? 0" />
                                    <span class="flex-1">#{{ $r->id }}</span>
                                    <x-ui.button variant="secondary" wire:click="mergeAlert({{ $r->id }})" class="!px-2 !py-1 text-xs">{{ __('analyst.cases.merge_action') }}</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <form wire:submit="mergeById" class="flex gap-2">
                        <input type="number" wire:model="mergeAlertId" min="1" placeholder="{{ __('analyst.cases.alert_id') }}" class="{{ $field }}">
                        <x-ui.button type="submit" variant="secondary">{{ __('analyst.cases.merge_action') }}</x-ui.button>
                    </form>
                    @error('mergeAlertId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </x-ui.card>

                <x-ui.card :title="__('analyst.cases.close')">
                    <textarea wire:model="closeReason" rows="2" class="{{ $field }}" placeholder="{{ __('analyst.cases.close_reason') }}"></textarea>
                    @error('closeReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs text-slate-400">{{ __('analyst.cases.high_value_hint', ['threshold' => number_format((float) app(\App\Services\Settings\SettingsService::class)->get('cases.high_value_threshold', 50000))]) }}</p>
                    <x-ui.button variant="danger" wire:click="closeCase" class="mt-3">{{ __('analyst.cases.close') }}</x-ui.button>
                </x-ui.card>
            @endunless
        </div>
    </div>
</div>
