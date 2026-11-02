<x-layouts.guest :title="__('common.reset_password')">
    <h1 class="mb-6 text-xl font-bold">{{ __('common.forgot_password') }}</h1>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('common.email')" :value="old('email')" required autofocus />
        <x-ui.button type="submit" class="w-full">{{ __('common.send_reset_link') }}</x-ui.button>
        <a href="{{ route('login') }}" class="block text-center text-sm text-brand-600 hover:underline">{{ __('common.login') }}</a>
    </form>
</x-layouts.guest>
