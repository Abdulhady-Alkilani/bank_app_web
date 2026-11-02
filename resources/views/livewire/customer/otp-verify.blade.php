<div class="mx-auto max-w-md space-y-4">
    @if ($outcome === 'verified')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-800" data-otp="verified">
            <p class="font-semibold">&#10003; {{ __('customer.otp.verified') }}</p>
            <div class="mt-3"><x-ui.button :href="route('portal.history')" variant="secondary">{{ __('customer.activity.title') }}</x-ui.button></div>
        </div>
    @elseif ($outcome === 'failed')
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-800" data-otp="failed">
            <p class="font-semibold">{{ __('customer.otp.failed') }}</p>
            <div class="mt-3"><x-ui.button :href="route('portal.transfer')" variant="secondary">{{ __('customer.otp.back') }}</x-ui.button></div>
        </div>
    @elseif ($outcome === 'expired')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-800" data-otp="expired">
            <p class="font-semibold">{{ __('customer.otp.expired') }}</p>
            <div class="mt-3"><x-ui.button :href="route('portal.transfer')" variant="secondary">{{ __('customer.otp.back') }}</x-ui.button></div>
        </div>
    @else
        <x-ui.card :title="__('customer.otp.heading')">
            <p class="text-sm text-slate-600">{{ __('customer.otp.intro') }}</p>

            @if ($challenge->transaction)
                <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                    {{ __('customer.otp.transfer_summary', [
                        'amount' => number_format((float) $challenge->transaction->amount, 2),
                        'beneficiary' => $challenge->transaction->beneficiary?->beneficiary_name ?? '-',
                    ]) }}
                </p>
            @endif

            @if ($devCode)
                <div class="mt-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800" data-dev-code>
                    {{ __('customer.otp.dev_banner') }} <span class="font-mono text-base font-bold" dir="ltr">{{ $devCode }}</span>
                </div>
            @endif

            <form wire:submit="verify" class="mt-4 space-y-3">
                <x-ui.input name="code" wire:model="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" :label="__('customer.otp.code')" dir="ltr" class="text-center text-xl tracking-widest" />
                @if ($error)
                    <p class="text-sm text-red-600" role="alert">{{ $error }}</p>
                @endif
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>{{ __('customer.otp.attempts_left', ['left' => $attemptsLeft]) }}</span>
                    <span x-data="{ s: {{ $secondsLeft }} }" x-init="setInterval(() => { if (s > 0) s-- }, 1000)">
                        {{ __('customer.otp.expires_in') }}
                        <span class="font-mono" dir="ltr" x-text="String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0')"></span>
                    </span>
                </div>
                <x-ui.button type="submit" class="w-full">{{ __('customer.otp.verify') }}</x-ui.button>
            </form>
        </x-ui.card>
    @endif
</div>
