@props(['score'])
@php
    $score = (int) $score;
    $color = $score >= 75 ? 'bg-red-100 text-red-800' : ($score >= 40 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800');
@endphp
<span {{ $attributes->class(['inline-flex min-w-10 justify-center rounded-md px-2 py-0.5 text-xs font-bold tabular-nums', $color]) }}>{{ $score }}</span>
