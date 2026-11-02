<x-layouts.app :title="__('common.dashboard')">
    <x-ui.card :title="__('common.role_area.'.$area)">
        <p class="text-sm text-slate-500">{{ __('common.welcome', ['name' => auth()->user()->name]) }}</p>
        <p class="mt-2 text-sm text-slate-400">{{ __('common.placeholder_text') }}</p>
    </x-ui.card>
</x-layouts.app>
