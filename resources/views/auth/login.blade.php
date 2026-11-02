<x-layouts.guest :title="__('common.login')">
    <h1 class="mb-6 text-xl font-bold">{{ __('common.login') }}</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('common.email')" :value="old('email')" required autofocus autocomplete="username" />
        <x-ui.input name="password" type="password" :label="__('common.password')" required autocomplete="current-password" />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600">
                {{ __('common.remember_me') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-brand-600 hover:underline">{{ __('common.forgot_password') }}</a>
        </div>

        <x-ui.button type="submit" class="w-full">{{ __('common.login') }}</x-ui.button>
    </form>
</x-layouts.guest>
