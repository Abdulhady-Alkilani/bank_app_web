<div class="space-y-4">
    <x-console.flash :message="$flash" :error="$errorMessage" />

    @if ($tempPassword)
        <x-console.secret :title="__('admin.users.temp_password_for', ['email' => $tempFor])" :hint="__('admin.shown_once')">
            <span data-testid="temp-password">{{ $tempPassword }}</span>
            <div class="pt-2"><x-ui.button variant="secondary" wire:click="dismissPassword">{{ __('admin.dismiss') }}</x-ui.button></div>
        </x-console.secret>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full max-w-xs">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.users.search') }}"
                   class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none">
        </div>
        <x-ui.button class="ms-auto" wire:click="create">{{ __('admin.users.add') }}</x-ui.button>
    </div>

    @if ($showForm)
        <x-ui.card :title="$editId ? __('admin.users.edit') : __('admin.users.add')">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" wire:model="name" :label="__('admin.users.name')" />
                    <x-ui.input name="email" type="email" wire:model="email" :label="__('common.email')" dir="ltr" />
                </div>
                <div>
                    <p class="mb-1 text-sm font-medium text-slate-700">{{ __('admin.users.roles') }}</p>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="roleIds" value="{{ $role->id }}" class="rounded border-slate-300">
                                {{ $role->label ?: $role->role_name }}
                            </label>
                        @endforeach
                    </div>
                    @error('roleIds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                @unless ($editId)
                    <p class="text-xs text-slate-500">{{ __('admin.users.password_hint') }}</p>
                @endunless
                <div class="flex gap-2">
                    <x-ui.button type="submit">{{ __('common.save') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('common.cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.users.name') }}</th><th>{{ __('common.email') }}</th><th>{{ __('admin.users.roles') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th>
        </x-slot:head>
        @forelse ($users as $user)
            <tr wire:key="user-{{ $user->id }}">
                <td class="font-medium">{{ $user->name }}</td>
                <td dir="ltr" class="text-start">{{ $user->email }}</td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($user->roles as $role)
                            <x-ui.badge :label="$role->label ?: $role->role_name" />
                        @endforeach
                    </div>
                </td>
                <td>
                    @if ($user->is_active)
                        <x-ui.badge color="bg-emerald-100 text-emerald-800" :label="__('admin.users.active')" />
                    @else
                        <x-ui.badge color="bg-red-100 text-red-800" :label="__('admin.users.inactive')" />
                    @endif
                </td>
                <td>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" wire:click="edit({{ $user->id }})">{{ __('admin.edit') }}</x-ui.button>
                        <x-ui.button variant="secondary" wire:click="resetPassword({{ $user->id }})" wire:confirm="{{ __('admin.users.reset_confirm') }}">{{ __('admin.users.reset_password') }}</x-ui.button>
                        @if ($user->id !== auth()->id())
                            <x-ui.button :variant="$user->is_active ? 'danger' : 'secondary'" wire:click="toggleActive({{ $user->id }})">
                                {{ $user->is_active ? __('admin.users.deactivate') : __('admin.users.activate') }}
                            </x-ui.button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ui.empty-state /></td></tr>
        @endforelse
    </x-ui.table>

    {{ $users->links() }}
</div>
