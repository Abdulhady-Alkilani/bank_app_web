@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

@php
    $classes = [
        'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-brand-500/40 disabled:cursor-not-allowed disabled:opacity-50',
        match ($variant) {
            'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
            'danger' => 'bg-red-600 text-white hover:bg-red-700',
            'ghost' => 'text-slate-600 hover:bg-slate-100',
            default => 'bg-brand-600 text-white hover:bg-brand-700',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
