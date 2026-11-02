<x-layouts.guest :title="__('common.reset_password')">
    <h1 class="mb-6 text-xl font-bold">{{ __('common.reset_password') }}</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input name="email" type="email" :label="__('common.email')" :value="old('email', $request->email)" required autofocus />
        <x-ui.input name="password" type="password" :label="__('common.new_password')" required autocomplete="new-password" />
        <x-ui.input name="password_confirmation" type="password" :label="__('common.password_confirmation')" required autocomplete="new-password" />
        <x-ui.button type="submit" class="w-full">{{ __('common.reset_password') }}</x-ui.button>
    </form>
</x-layouts.guest>
