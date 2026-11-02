@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
    $user = auth()->user();
    $nav = $user ? \App\Support\Navigation::for($user) : [];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ __('common.app_name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased" x-data="{ sidebar: false }">
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <div x-show="sidebar" x-cloak x-on:click="sidebar = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>
    <aside :class="sidebar ? 'flex' : 'hidden lg:flex'"
           class="fixed inset-y-0 start-0 z-40 w-64 shrink-0 flex-col border-e border-slate-200 bg-white lg:sticky lg:top-0 lg:h-screen">
        <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5">
            <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">ك</span>
            <span class="text-sm font-semibold leading-tight">{{ __('common.app_name') }}</span>
        </div>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
            @foreach ($nav as $group)
                <div>
                    <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $group['title'] }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $active = request()->routeIs($item['route']); @endphp
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   @class([
                                       'flex items-center rounded-lg px-3 py-2 text-sm transition',
                                       'bg-brand-50 font-semibold text-brand-700' => $active,
                                       'text-slate-600 hover:bg-slate-100' => ! $active,
                                   ])>{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" x-on:click="sidebar = true" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="{{ __('common.open_menu') }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="truncate text-base font-semibold sm:text-lg">{{ $title ?? __('common.dashboard') }}</h1>

            <div class="ms-auto flex items-center gap-2">
                <a href="{{ route('locale.switch', $otherLocale) }}" class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">
                    {{ $otherLocale === 'ar' ? __('common.arabic') : __('common.english') }}
                </a>

                {{-- Notification bell slot: Agent C provides App\Livewire\NotificationBell. --}}
                @if (class_exists(\App\Livewire\NotificationBell::class))
                    <livewire:notification-bell />
                @else
                    <span class="rounded-lg p-2 text-slate-400" title="{{ __('common.notifications') }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                    </span>
                @endif

                @if ($user)
                    <div class="flex items-center gap-2 border-s border-slate-200 ps-3">
                        <span class="hidden text-sm text-slate-600 sm:inline">{{ $user->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100">{{ __('common.logout') }}</button>
                        </form>
                    </div>
                @endif
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            @foreach ([
                'status' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
                'error' => 'border-red-200 bg-red-50 text-red-800',
            ] as $key => $classes)
                @if (session($key))
                    <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}" role="alert">{{ session($key) }}</div>
                @endif
            @endforeach

            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
