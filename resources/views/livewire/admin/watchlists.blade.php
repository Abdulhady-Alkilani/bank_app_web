<div class="space-y-4">
    <x-console.flash :message="$flash" />

    <div class="flex flex-wrap items-end gap-3">
        <x-console.select name="filterType" wire:model.live="filterType" class="!w-auto">
            <option value="">{{ __('admin.watchlists.all_lists') }}</option>
            @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </x-console.select>
        <x-console.select name="filterEntity" wire:model.live="filterEntity" class="!w-auto">
            <option value="">{{ __('admin.watchlists.all_entities') }}</option>
            @foreach ($entities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </x-console.select>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('common.search') }}"
               class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 focus:outline-none">
        <x-ui.button class="ms-auto" wire:click="create">{{ __('admin.watchlists.add') }}</x-ui.button>
    </div>

    @if ($showForm)
        <x-ui.card :title="$editId ? __('admin.watchlists.edit') : __('admin.watchlists.add')">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-console.select name="listType" wire:model="listType" :label="__('admin.watchlists.list_type')">
                        @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </x-console.select>
                    <x-console.select name="entityType" wire:model="entityType" :label="__('admin.watchlists.entity_type')">
                        @foreach ($entities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </x-console.select>
                    <x-ui.input name="entityValue" wire:model="entityValue" :label="__('admin.watchlists.value')" dir="ltr" />
                </div>
                <x-ui.input name="reason" wire:model="reason" :label="__('admin.watchlists.reason')" />
                <div class="flex gap-2">
                    <x-ui.button type="submit">{{ __('common.save') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('common.cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.watchlists.list_type') }}</th><th>{{ __('admin.watchlists.entity_type') }}</th><th>{{ __('admin.watchlists.value') }}</th>
            <th>{{ __('admin.watchlists.reason') }}</th><th>{{ __('common.actions') }}</th>
        </x-slot:head>
        @forelse ($entries as $entry)
            <tr wire:key="wl-{{ $entry->id }}">
                <td><x-ui.badge :color="$entry->list_type === \App\Enums\WatchlistType::Blacklist ? 'bg-red-100 text-red-800' : 'bg-emerald-100 text-emerald-800'" :label="$entry->list_type->label()" /></td>
                <td>{{ $entry->entity_type->label() }}</td>
                <td class="font-mono" dir="ltr">{{ $entry->entity_value }}</td>
                <td>{{ $entry->reason }}</td>
                <td>
                    <div class="flex gap-2">
                        <x-ui.button variant="secondary" wire:click="edit({{ $entry->id }})">{{ __('admin.edit') }}</x-ui.button>
                        <x-ui.button variant="danger" wire:click="delete({{ $entry->id }})" wire:confirm="{{ __('admin.delete_confirm') }}">{{ __('admin.delete') }}</x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-ui.empty-state /></td></tr>
        @endforelse
    </x-ui.table>

    {{ $entries->links() }}
</div>
