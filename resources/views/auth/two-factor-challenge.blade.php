<x-layouts.guest :title="__('common.tfa.challenge_title')">
    <div x-data="{ recovery: false }">
        <h1 class="mb-2 text-xl font-bold">{{ __('common.tfa.challenge_title') }}</h1>
        <p class="mb-6 text-sm text-slate-500">{{ __('common.tfa.challenge_hint') }}</p>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf
            <div x-show="! recovery">
                <x-ui.input name="code" inputmode="numeric" autocomplete="one-time-code" :label="__('common.tfa.code')" x-bind:required="! recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-ui.input name="recovery_code" autocomplete="one-time-code" :label="__('common.tfa.recovery_code')" x-bind:required="recovery" />
            </div>

            <x-ui.button type="submit" class="w-full">{{ __('common.confirm') }}</x-ui.button>

            <button type="button" class="w-full text-center text-sm text-brand-600 hover:underline" x-on:click="recovery = ! recovery"
                    x-text="recovery ? @js(__('common.tfa.use_code')) : @js(__('common.tfa.use_recovery'))"></button>
        </form>
    </div>
</x-layouts.guest>
