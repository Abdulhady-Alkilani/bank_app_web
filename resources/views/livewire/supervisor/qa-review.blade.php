<div class="space-y-4">
    <x-console.flash :message="$flash" />

    <x-ui.card>
        <form wire:submit="draw" class="flex flex-wrap items-end gap-3">
            <x-ui.input name="from" type="date" wire:model="from" :label="__('supervisor.qa.from')" />
            <x-ui.input name="to" type="date" wire:model="to" :label="__('supervisor.qa.to')" />
            <x-ui.button type="submit">{{ __('supervisor.qa.draw') }}</x-ui.button>
            <p class="text-xs text-slate-400">{{ __('supervisor.qa.rate_hint', ['rate' => $rate * 100]) }}</p>
        </form>
    </x-ui.card>

    @forelse ($alerts as $alert)
        @php $review = $reviews->get($alert->id); @endphp
        <x-ui.card wire:key="qa-{{ $alert->id }}" :title="__('supervisor.qa.alert', ['id' => $alert->id])">
            <x-slot:actions>
                @if ($review)
                    <x-ui.badge :color="($review->new_values['verdict'] ?? '') === 'agree' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                                :label="__('supervisor.qa.verdict.'.($review->new_values['verdict'] ?? 'agree'))" />
                @endif
            </x-slot:actions>
            <dl class="grid gap-2 text-sm sm:grid-cols-4">
                <div><dt class="text-slate-500">{{ __('supervisor.qa.decision') }}</dt><dd class="font-medium">{{ $alert->decision?->label() ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('supervisor.qa.decided_by') }}</dt><dd class="font-medium">{{ $alert->decider?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('supervisor.qa.score') }}</dt><dd class="font-medium">{{ $alert->riskScore?->score ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">{{ __('supervisor.qa.decided_at') }}</dt><dd class="font-medium" dir="ltr">{{ $alert->decided_at?->format('Y-m-d H:i') }}</dd></div>
            </dl>
            @if ($alert->decision_reason)
                <p class="mt-2 text-sm text-slate-600">{{ $alert->decision_reason }}</p>
            @endif
            <div class="mt-3 space-y-2">
                <x-console.textarea :name="'comments.'.$alert->id" wire:model="comments.{{ $alert->id }}" :placeholder="__('supervisor.qa.comment')" />
                <div class="flex gap-2">
                    <x-ui.button wire:click="review({{ $alert->id }}, 'agree')">{{ __('supervisor.qa.agree') }}</x-ui.button>
                    <x-ui.button variant="danger" wire:click="review({{ $alert->id }}, 'disagree')">{{ __('supervisor.qa.disagree') }}</x-ui.button>
                </div>
                @if ($review && ($review->new_values['comment'] ?? '') !== '')
                    <p class="text-xs text-slate-500">{{ __('supervisor.qa.last_comment') }}: {{ $review->new_values['comment'] }}</p>
                @endif
            </div>
        </x-ui.card>
    @empty
        <x-ui.empty-state :title="__('supervisor.qa.empty')" :description="__('supervisor.qa.empty_hint')" />
    @endforelse
</div>
