@props(['label', 'value', 'hint' => null, 'tone' => 'default'])

@php
    $valueColor = match ($tone) {
        'danger' => 'text-red-600',
        'warning' => 'text-amber-600',
        'success' => 'text-emerald-600',
        default => 'text-slate-900',
    };
@endphp

<div {{ $attributes->class('rounded-xl border border-slate-200 bg-white p-5 shadow-sm') }}>
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold {{ $valueColor }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
    {{ $slot }}
</div>
