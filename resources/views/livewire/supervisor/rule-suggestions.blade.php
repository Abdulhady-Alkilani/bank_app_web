<div class="space-y-6">
    <x-console.flash :message="$flash" />

    @forelse ($pending as $s)
        <x-ui.card wire:key="sug-{{ $s->id }}" :title="$s->title">
            <p class="text-xs text-slate-500">{{ __('supervisor.suggestions.by', ['name' => $s->suggester?->name]) }} — <span dir="ltr">{{ $s->created_at?->format('Y-m-d H:i') }}</span></p>
            @if ($s->description)<p class="mt-2 text-sm">{{ $s->description }}</p>@endif
            <pre class="mt-3 overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100" dir="ltr">{{ json_encode($s->conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            <div class="mt-3 space-y-2">
                <x-ui.input :name="'notes.'.$s->id" wire:model="notes.{{ $s->id }}" :placeholder="__('supervisor.suggestions.note')" />
                <div class="flex gap-2">
                    <x-ui.button wire:click="accept({{ $s->id }})">{{ __('supervisor.suggestions.accept') }}</x-ui.button>
                    <x-ui.button variant="danger" wire:click="reject({{ $s->id }})">{{ __('supervisor.suggestions.reject') }}</x-ui.button>
                </div>
                <p class="text-xs text-slate-400">{{ __('supervisor.suggestions.accept_hint') }}</p>
            </div>
        </x-ui.card>
    @empty
        <x-ui.empty-state :title="__('supervisor.suggestions.none')" />
    @endforelse

    @if ($reviewed->isNotEmpty())
        <x-ui.card :title="__('supervisor.suggestions.history')" :padded="false">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-slot:head>
                    <th>{{ __('supervisor.suggestions.title_col') }}</th><th>{{ __('common.status') }}</th><th>{{ __('supervisor.suggestions.reviewer') }}</th><th>{{ __('supervisor.suggestions.note_col') }}</th>
                </x-slot:head>
                @foreach ($reviewed as $s)
                    <tr wire:key="rev-{{ $s->id }}">
                        <td>{{ $s->title }}</td>
                        <td><x-ui.badge :color="$s->status === \App\Enums\RuleSuggestionStatus::Accepted ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'" :label="$s->status->label()" /></td>
                        <td>{{ $s->reviewer?->name }}</td>
                        <td>{{ $s->review_note }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
