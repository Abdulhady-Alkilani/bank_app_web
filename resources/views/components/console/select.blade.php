@props(['label' => null, 'name'])

<div>
    @if ($label)
        <label for="{{ $attributes->get('id', $name) }}" class="mb-1 block text-sm font-medium text-slate-700">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $attributes->get('id', $name) }}"
        {{ $attributes->except('id')->class('block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none') }}>
        {{ $slot }}
    </select>
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
