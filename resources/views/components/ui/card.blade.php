@props(['title' => null, 'padded' => true])

<section {{ $attributes->class('rounded-xl border border-slate-200 bg-white shadow-sm') }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
            @if ($title)
                <h2 class="text-sm font-semibold text-slate-700">{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-5' => $padded])>{{ $slot }}</div>
</section>
