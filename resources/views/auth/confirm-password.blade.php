<x-layouts.guest :title="__('common.confirm_password')">
    <h1 class="mb-2 text-xl font-bold">{{ __('common.confirm_password') }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('common.confirm_password_hint') }}</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf
        <x-ui.input name="password" type="password" :label="__('common.password')" required autofocus autocomplete="current-password" />
        <x-ui.button type="submit" class="w-full">{{ __('common.confirm') }}</x-ui.button>
    </form>
</x-layouts.guest>
