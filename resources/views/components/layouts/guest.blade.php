@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
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
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <div class="mb-6 flex items-center gap-3">
        <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-lg font-bold text-white">ك</span>
        <span class="text-base font-semibold">{{ __('common.app_name') }}</span>
    </div>

    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </div>

    <a href="{{ route('locale.switch', $otherLocale) }}" class="mt-4 text-sm text-slate-500 hover:text-slate-700">
        {{ $otherLocale === 'ar' ? __('common.arabic') : __('common.english') }}
    </a>
</div>
</body>
</html>
