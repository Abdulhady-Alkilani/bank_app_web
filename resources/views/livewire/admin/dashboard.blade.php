<div class="space-y-6" wire:poll.30s>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('admin.dashboard.users')" :value="$users" />
        <x-ui.stat :label="__('admin.dashboard.active_rules')" :value="$activeRules" />
        <x-ui.stat :label="__('admin.dashboard.api_clients')" :value="$apiClients" />
        <x-ui.stat :label="__('admin.dashboard.alerts_24h')" :value="$alerts24h" />
        <x-ui.stat :label="__('admin.dashboard.ai_status')" :value="__('admin.health.ai.'.$aiStatus)"
                   :tone="match ($aiStatus) { 'up' => 'success', 'down' => 'danger', default => 'warning' }" />
        <x-ui.stat :label="__('admin.dashboard.queue_backlog')" :value="$backlog" :tone="$backlog > 100 ? 'warning' : 'default'" />
        <x-ui.stat :label="__('admin.dashboard.failed_jobs')" :value="$failed" :tone="$failed > 0 ? 'danger' : 'default'" />
    </div>

    <x-ui.card :title="__('admin.dashboard.shortcuts')">
        <div class="flex flex-wrap gap-2">
            @foreach (['admin.users' => 'users', 'admin.rules' => 'rules', 'admin.settings' => 'settings', 'admin.health' => 'health', 'admin.audit' => 'audit', 'admin.backups' => 'backups'] as $route => $key)
                @if (Route::has($route))
                    <x-ui.button variant="secondary" :href="route($route)">{{ __('common.nav.'.$key) }}</x-ui.button>
                @endif
            @endforeach
        </div>
    </x-ui.card>
</div>
