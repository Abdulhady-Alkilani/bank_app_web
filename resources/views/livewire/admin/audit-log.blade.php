<div class="space-y-4">
    <x-ui.card>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-console.select name="userId" wire:model.live="userId" :label="__('admin.audit.user')">
                <option value="">{{ __('admin.audit.all') }}</option>
                @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
            </x-console.select>
            <x-ui.input name="action" wire:model.live.debounce.400ms="action" :label="__('admin.audit.action')" dir="ltr" placeholder="user.created" />
            <x-console.select name="entityType" wire:model.live="entityType" :label="__('admin.audit.entity')">
                <option value="">{{ __('admin.audit.all') }}</option>
                @foreach ($entityTypes as $type)<option value="{{ $type }}">{{ class_basename($type) }}</option>@endforeach
            </x-console.select>
            <x-ui.input name="from" type="date" wire:model.live="from" :label="__('admin.audit.from')" />
            <x-ui.input name="to" type="date" wire:model.live="to" :label="__('admin.audit.to')" />
        </div>
        @if ($canExport)
            <div class="mt-4 flex gap-2">
                <x-ui.button variant="secondary" wire:click="exportCsv">{{ __('admin.audit.export_csv') }}</x-ui.button>
                <x-ui.button variant="secondary" wire:click="exportExcel">{{ __('admin.audit.export_excel') }}</x-ui.button>
            </div>
        @endif
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <th>#</th><th>{{ __('admin.audit.time') }}</th><th>{{ __('admin.audit.user') }}</th><th>{{ __('admin.audit.action') }}</th>
            <th>{{ __('admin.audit.entity') }}</th><th>IP</th><th></th>
        </x-slot:head>
        @forelse ($logs as $log)
            <tr wire:key="log-{{ $log->id }}" @class(['bg-brand-50' => $selectedId === $log->id])>
                <td>{{ $log->id }}</td>
                <td dir="ltr" class="whitespace-nowrap text-start">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                <td>{{ $log->user?->name ?? '—' }}</td>
                <td class="font-mono text-xs" dir="ltr">{{ $log->action }}</td>
                <td>{{ $log->entity_type ? class_basename($log->entity_type).' #'.$log->entity_id : '—' }}</td>
                <td dir="ltr">{{ $log->ip_address }}</td>
                <td><x-ui.button variant="ghost" wire:click="select({{ $log->id }})">{{ __('common.details') }}</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-ui.empty-state /></td></tr>
        @endforelse
    </x-ui.table>

    {{ $logs->links() }}

    @if ($selected)
        <x-ui.card :title="__('admin.audit.detail', ['id' => $selected->id])">
            @if ($diff === [])
                <p class="text-sm text-slate-500">{{ __('admin.audit.no_values') }}</p>
            @else
                <x-ui.table>
                    <x-slot:head><th>{{ __('admin.audit.field') }}</th><th>{{ __('admin.audit.old') }}</th><th>{{ __('admin.audit.new') }}</th></x-slot:head>
                    @foreach ($diff as $field => $values)
                        <tr>
                            <td class="font-mono text-xs" dir="ltr">{{ $field }}</td>
                            <td class="bg-red-50/60 font-mono text-xs break-all" dir="ltr">{{ is_scalar($values['old']) || $values['old'] === null ? ($values['old'] ?? '—') : json_encode($values['old'], JSON_UNESCAPED_UNICODE) }}</td>
                            <td class="bg-emerald-50/60 font-mono text-xs break-all" dir="ltr">{{ is_scalar($values['new']) || $values['new'] === null ? ($values['new'] ?? '—') : json_encode($values['new'], JSON_UNESCAPED_UNICODE) }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif
</div>
