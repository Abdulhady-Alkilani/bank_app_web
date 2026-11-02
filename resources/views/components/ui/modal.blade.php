{{-- Open with: $dispatch('open-modal', 'name') — close with: $dispatch('close-modal', 'name') --}}
@props(['name', 'title' => null, 'maxWidth' => 'lg'])

@php
    $width = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'xl' => 'max-w-3xl',
        default => 'max-w-lg',
    };
@endphp

<div x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
     x-on:keydown.escape.window="show = false"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-slate-900/50" x-on:click="show = false"></div>
    <div {{ $attributes->class(['relative w-full rounded-xl bg-white shadow-xl', $width]) }}>
        @if ($title)
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h3 class="text-base font-semibold">{{ $title }}</h3>
                <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-600" aria-label="{{ __('common.cancel') }}">&times;</button>
            </header>
        @endif
        <div class="p-5">{{ $slot }}</div>
        @isset($footer)
            <footer class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">{{ $footer }}</footer>
        @endisset
    </div>
</div>
