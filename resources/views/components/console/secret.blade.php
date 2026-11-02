@props(['title', 'hint' => null])

<div {{ $attributes->class('rounded-lg border border-amber-300 bg-amber-50 p-4') }}>
    <p class="text-sm font-semibold text-amber-900">{{ $title }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-amber-800">{{ $hint }}</p>
    @endif
    <div class="mt-2 space-y-1 font-mono text-sm break-all text-slate-900" dir="ltr">{{ $slot }}</div>
</div>
