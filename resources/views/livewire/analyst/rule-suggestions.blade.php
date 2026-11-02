@php
    $field = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none';
@endphp
<div class="grid gap-6 lg:grid-cols-5">
    <x-ui.card :title="__('analyst.rules.new')" class="lg:col-span-3">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
        @endif
        <form wire:submit="submit" class="space-y-4">
            <div>
                <label for="title" class="mb-1 block text-sm font-medium text-slate-700">{{ __('analyst.rules.title_label') }}</label>
                <input id="title" type="text" wire:model="title" class="{{ $field }}">
                @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="mb-1 block text-sm font-medium text-slate-700">{{ __('analyst.rules.description') }}</label>
                <textarea id="description" rows="3" wire:model="description" class="{{ $field }}"></textarea>
                @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <p class="mb-1 text-sm font-medium text-slate-700">{{ __('analyst.rules.event_types') }}</p>
                <div class="flex flex-wrap gap-3">
                    @foreach ($eventTypeOptions as $value => $label)
                        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" wire:model="eventTypes" value="{{ $value }}" class="rounded border-slate-300 text-brand-600"> {{ $label }}</label>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center gap-3">
                    <p class="text-sm font-medium text-slate-700">{{ __('analyst.rules.conditions') }}</p>
                    <select wire:model="match" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs">
                        <option value="all">{{ __('analyst.rules.match_all') }}</option>
                        <option value="any">{{ __('analyst.rules.match_any') }}</option>
                    </select>
                </div>
                <div class="space-y-2">
                    @foreach ($conditions as $i => $row)
                        <div class="grid grid-cols-12 gap-2" wire:key="cond-{{ $i }}">
                            <select wire:model="conditions.{{ $i }}.feature" class="{{ $field }} col-span-12 sm:col-span-5">
                                @foreach ($features as $feature)<option value="{{ $feature }}">{{ \Illuminate\Support\Facades\Lang::has('analyst.features.'.$feature) ? __('analyst.features.'.$feature) : $feature }}</option>@endforeach
                            </select>
                            <select wire:model="conditions.{{ $i }}.op" class="{{ $field }} col-span-4 sm:col-span-2" dir="ltr">
                                @foreach ($operators as $op)<option value="{{ $op }}">{{ $op }}</option>@endforeach
                            </select>
                            <input type="text" wire:model="conditions.{{ $i }}.value" placeholder="{{ __('analyst.rules.value') }}" class="{{ $field }} col-span-6 sm:col-span-4" dir="ltr">
                            <button type="button" wire:click="removeCondition({{ $i }})" class="col-span-2 text-xl text-slate-400 hover:text-red-600 sm:col-span-1" aria-label="{{ __('analyst.cases.delete') }}">&times;</button>
                            @error("conditions.$i.value")<p class="col-span-12 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <x-ui.button variant="ghost" wire:click="addCondition" class="mt-2">+ {{ __('analyst.rules.add_condition') }}</x-ui.button>
                <p class="mt-1 text-xs text-slate-400">{{ __('analyst.rules.in_hint') }}</p>
            </div>

            <x-ui.button type="submit">{{ __('analyst.rules.submit') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card :title="__('analyst.rules.mine')" :padded="false" class="lg:col-span-2">
        @if ($suggestions->isEmpty())
            <p class="p-5 text-sm text-slate-500">{{ __('analyst.rules.none') }}</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($suggestions as $s)
                    <li class="space-y-1 px-5 py-3" wire:key="sug-{{ $s->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ $s->title }}</span>
                            <x-ui.badge :value="$s->status" />
                        </div>
                        <p class="text-xs text-slate-400">{{ $s->created_at?->diffForHumans() }}</p>
                        @if ($s->review_note)<p class="text-xs text-slate-600">{{ $s->review_note }}</p>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
