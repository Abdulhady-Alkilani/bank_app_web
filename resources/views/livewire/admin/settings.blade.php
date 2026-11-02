<form wire:submit="save" class="space-y-6">
    <x-console.flash :message="$flash" />

    <x-ui.card :title="__('admin.settings.thresholds')">
        <p class="mb-4 text-sm text-slate-500">{{ __('admin.settings.thresholds_hint') }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="form.allow_max" type="number" wire:model="form.allow_max" :label="__('admin.settings.allow_max')" min="0" max="99" />
            <x-ui.input name="form.challenge_max" type="number" wire:model="form.challenge_max" :label="__('admin.settings.challenge_max')" min="1" max="100" />
        </div>
    </x-ui.card>

    <x-ui.card :title="__('admin.settings.weights')">
        <p class="mb-4 text-sm text-slate-500">{{ __('admin.settings.weights_hint') }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="form.rule_weight" type="number" step="0.01" wire:model="form.rule_weight" :label="__('admin.settings.rule_weight')" />
            <x-ui.input name="form.model_weight" type="number" step="0.01" wire:model="form.model_weight" :label="__('admin.settings.model_weight')" />
        </div>
    </x-ui.card>

    <x-ui.card :title="__('admin.settings.operations')">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="form.otp_ttl" type="number" wire:model="form.otp_ttl" :label="__('admin.settings.otp_ttl')" />
            <x-ui.input name="form.otp_attempts" type="number" wire:model="form.otp_attempts" :label="__('admin.settings.otp_attempts')" />
            <x-ui.input name="form.transfer_limit" type="number" step="0.01" wire:model="form.transfer_limit" :label="__('admin.settings.transfer_limit')" />
            <x-ui.input name="form.high_value" type="number" step="0.01" wire:model="form.high_value" :label="__('admin.settings.high_value')" />
            <x-ui.input name="form.qa_rate" type="number" step="0.01" wire:model="form.qa_rate" :label="__('admin.settings.qa_rate')" />
            <x-ui.input name="form.capacity" type="number" wire:model="form.capacity" :label="__('admin.settings.capacity')" />
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="form.require_2fa" class="rounded border-slate-300">
            {{ __('admin.settings.require_2fa') }}
        </label>
    </x-ui.card>

    <x-ui.button type="submit">{{ __('common.save') }}</x-ui.button>
</form>
