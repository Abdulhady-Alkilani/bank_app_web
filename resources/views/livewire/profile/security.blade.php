<div class="mx-auto max-w-2xl space-y-4">
    <x-ui.card :title="__('common.tfa.title')">
        <p class="text-sm text-slate-500">{{ __('common.tfa.description') }}</p>

        <div class="mt-4 flex items-center gap-3">
            @if ($confirmed)
                <x-ui.badge color="bg-emerald-100 text-emerald-800" :label="__('common.tfa.enabled')" />
            @elseif ($hasSecret)
                <x-ui.badge color="bg-amber-100 text-amber-800" :label="__('common.tfa.pending')" />
            @else
                <x-ui.badge :label="__('common.tfa.disabled')" />
            @endif
        </div>

        @if (! $hasSecret)
            <x-ui.button class="mt-4" wire:click="enable">{{ __('common.tfa.enable') }}</x-ui.button>
        @elseif (! $confirmed)
            <div class="mt-4 space-y-4">
                <p class="text-sm text-slate-600">{{ __('common.tfa.scan') }}</p>
                <div class="inline-block rounded-lg border border-slate-200 bg-white p-3" dir="ltr">{!! $qrSvg !!}</div>
                <p class="text-xs text-slate-500">{{ __('common.tfa.setup_key') }}: <span class="font-mono" dir="ltr">{{ $setupKey }}</span></p>

                <form wire:submit="confirmSetup" class="flex items-start gap-2">
                    <div class="flex-1">
                        <input type="text" wire:model="code" inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('common.tfa.code') }}"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                        @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-ui.button type="submit">{{ __('common.tfa.confirm') }}</x-ui.button>
                </form>
                <x-ui.button variant="ghost" wire:click="disable">{{ __('common.cancel') }}</x-ui.button>
            </div>
        @else
            <div class="mt-4 flex flex-wrap gap-2">
                <x-ui.button variant="secondary" wire:click="toggleRecoveryCodes">{{ __('common.tfa.recovery_codes') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="regenerateRecoveryCodes">{{ __('common.tfa.regenerate') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="disable">{{ __('common.tfa.disable') }}</x-ui.button>
            </div>

            @if ($recoveryCodes)
                <div class="mt-4 rounded-lg bg-slate-50 p-4">
                    <p class="mb-2 text-xs text-slate-500">{{ __('common.tfa.recovery_hint') }}</p>
                    <ul class="grid grid-cols-2 gap-1 font-mono text-sm" dir="ltr">
                        @foreach ($recoveryCodes as $recoveryCode)
                            <li>{{ $recoveryCode }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </x-ui.card>
</div>
