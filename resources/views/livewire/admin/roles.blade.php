<div class="space-y-4">
    <x-console.flash :message="$flash" :error="$errorMessage" />

    <p class="text-sm text-slate-500">{{ __('admin.roles.hint') }}</p>

    <form wire:submit="save" class="space-y-4">
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.roles.permission') }}</th>
                @foreach ($roles as $role)
                    <th class="text-center">{{ $role->label ?: $role->role_name }}</th>
                @endforeach
            </x-slot:head>
            @foreach ($permissions as $permission)
                <tr wire:key="perm-{{ $permission->id }}">
                    <td>
                        <p class="font-medium">{{ __('admin.permissions')[$permission->name] ?? $permission->name }}</p>
                        <p class="font-mono text-xs text-slate-400" dir="ltr">{{ $permission->name }}</p>
                    </td>
                    @foreach ($roles as $role)
                        <td class="text-center">
                            <input type="checkbox" wire:model="grid.{{ $role->id }}" value="{{ $permission->name }}"
                                   class="size-4 rounded border-slate-300" aria-label="{{ $role->slug }} {{ $permission->name }}">
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.button type="submit">{{ __('common.save') }}</x-ui.button>
    </form>
</div>
