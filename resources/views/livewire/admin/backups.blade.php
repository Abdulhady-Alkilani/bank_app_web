<div class="space-y-4">
    <x-console.flash :message="$flash" :error="$errorMessage" />

    <div class="flex items-center gap-3">
        <p class="text-sm text-slate-500">{{ __('admin.backups.hint') }}</p>
        <x-ui.button class="ms-auto" wire:click="create" wire:loading.attr="disabled" wire:target="create">
            <span wire:loading.remove wire:target="create">{{ __('admin.backups.run') }}</span>
            <span wire:loading wire:target="create">{{ __('admin.backups.running') }}</span>
        </x-ui.button>
    </div>

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.backups.file') }}</th><th>{{ __('admin.backups.size') }}</th><th>{{ __('admin.backups.date') }}</th><th>{{ __('common.actions') }}</th>
        </x-slot:head>
        @forelse ($files as $file)
            <tr wire:key="backup-{{ $file['name'] }}">
                <td class="font-mono" dir="ltr">{{ $file['name'] }}</td>
                <td dir="ltr">{{ number_format($file['size'] / 1024, 1) }} KB</td>
                <td dir="ltr">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i:s') }}</td>
                <td><x-ui.button variant="secondary" wire:click="download('{{ $file['name'] }}')">{{ __('admin.backups.download') }}</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="4"><x-ui.empty-state :title="__('admin.backups.none')" /></td></tr>
        @endforelse
    </x-ui.table>

    <p class="text-xs text-slate-400">{{ __('admin.backups.restore_hint') }} <code dir="ltr">php artisan bank:backup:restore {file} --force</code></p>
</div>
