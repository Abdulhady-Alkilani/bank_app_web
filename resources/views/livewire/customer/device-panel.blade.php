<x-ui.card :title="__('customer.device.title')">
    <p class="mb-3 text-xs text-slate-500">{{ __('customer.device.hint') }}</p>

    @if ($isNew)
        <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('customer.device.unknown_active') }}</div>
    @endif

    <form wire:submit="save" class="space-y-3">
        <x-ui.input name="fingerprint" wire:model="fingerprint" :label="__('customer.device.fingerprint')" dir="ltr" />
        <div class="grid grid-cols-2 gap-3">
            <x-ui.input name="ip" wire:model="ip" :label="__('customer.device.ip')" dir="ltr" />
            <x-ui.input name="city" wire:model="city" :label="__('customer.device.city')" />
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button type="submit" variant="secondary">{{ __('common.save') }}</x-ui.button>
            <x-ui.button wire:click="useNewDevice" variant="danger">{{ __('customer.device.use_new') }}</x-ui.button>
            <x-ui.button wire:click="useTrustedDevice" variant="ghost">{{ __('customer.device.use_trusted') }}</x-ui.button>
        </div>
    </form>
</x-ui.card>
