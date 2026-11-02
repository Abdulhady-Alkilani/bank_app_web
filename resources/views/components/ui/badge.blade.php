@props(['value' => null, 'color' => null, 'label' => null])

@php
    // $value may be a Priority / Decision (or any backed enum with label()); color() is used when available.
    if ($value instanceof \BackedEnum) {
        $label ??= method_exists($value, 'label') ? $value->label() : (string) $value->value;
        $color ??= method_exists($value, 'color') ? $value->color() : null;
    } else {
        $label ??= is_scalar($value) ? (string) $value : null;
    }
    $color ??= 'bg-slate-100 text-slate-700';
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', $color]) }}>{{ $label ?? $slot }}</span>
