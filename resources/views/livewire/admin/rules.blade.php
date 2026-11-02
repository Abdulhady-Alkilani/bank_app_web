<div class="space-y-4">
    <x-console.flash :message="$flash" />

    <div class="flex">
        <x-ui.button class="ms-auto" wire:click="create">{{ __('admin.rules.add') }}</x-ui.button>
    </div>

    @if ($showForm)
        <x-ui.card :title="$editId ? __('admin.rules.edit') : __('admin.rules.add')">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="ruleName" wire:model="ruleName" :label="__('admin.rules.name')" />
                    <x-console.select name="action" wire:model="action" :label="__('admin.rules.action')">
                        @foreach ($actions as $a)
                            <option value="{{ $a }}">{{ __('admin.rules.actions.'.$a) }}</option>
                        @endforeach
                    </x-console.select>
                    <x-ui.input name="weight" type="number" wire:model="weight" :label="__('admin.rules.weight')" min="0" max="100" />
                    <x-ui.input name="priority" type="number" wire:model="priority" :label="__('admin.rules.priority')" min="0" />
                </div>
                <x-console.textarea name="description" wire:model="description" :label="__('admin.rules.description')" />

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="isActive" class="rounded border-slate-300"> {{ __('admin.rules.active') }}
                </label>

                <div>
                    <p class="mb-1 text-sm font-medium text-slate-700">{{ __('admin.rules.event_types') }}</p>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($eventOptions as $value => $label)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model.live="eventTypes" value="{{ $value }}" class="rounded border-slate-300"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ __('admin.rules.event_types_hint') }}</p>
                </div>

                <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm font-medium text-slate-700">{{ __('admin.rules.conditions') }}</p>
                        <x-console.select name="logic" wire:model.live="logic" class="!w-auto">
                            <option value="all">{{ __('admin.rules.logic_all') }}</option>
                            <option value="any">{{ __('admin.rules.logic_any') }}</option>
                        </x-console.select>
                    </div>

                    @foreach ($rows as $i => $row)
                        <div class="grid items-start gap-2 sm:grid-cols-[2fr_1fr_2fr_auto]" wire:key="row-{{ $i }}">
                            <x-console.select :name="'rows.'.$i.'.feature'" wire:model.live="rows.{{ $i }}.feature">
                                @foreach ($features as $feature)
                                    <option value="{{ $feature }}">{{ $feature }}</option>
                                @endforeach
                            </x-console.select>
                            <x-console.select :name="'rows.'.$i.'.op'" wire:model.live="rows.{{ $i }}.op" dir="ltr">
                                @foreach ($operators as $op)
                                    <option value="{{ $op }}">{{ $op }}</option>
                                @endforeach
                            </x-console.select>
                            <x-ui.input :name="'rows.'.$i.'.value'" wire:model.live.debounce.300ms="rows.{{ $i }}.value" dir="ltr"
                                        :placeholder="$row['op'] === 'in' ? __('admin.rules.value_list_hint') : __('admin.rules.value')" />
                            <x-ui.button variant="ghost" wire:click="removeRow({{ $i }})" :aria-label="__('admin.remove')">&times;</x-ui.button>
                        </div>
                    @endforeach

                    <x-ui.button variant="secondary" wire:click="addRow">{{ __('admin.rules.add_condition') }}</x-ui.button>
                    @error('conditions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="mb-1 text-sm font-medium text-slate-700">{{ __('admin.rules.preview') }}</p>
                    <pre class="overflow-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100" dir="ltr">{{ $preview }}</pre>
                    @foreach ($previewErrors as $error)
                        <p class="mt-1 text-xs text-amber-700">{{ $error }}</p>
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <x-ui.button type="submit">{{ __('common.save') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('common.cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.rules.name') }}</th><th>{{ __('admin.rules.action') }}</th><th>{{ __('admin.rules.weight') }}</th>
            <th>{{ __('admin.rules.priority') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th>
        </x-slot:head>
        @forelse ($rules as $rule)
            <tr wire:key="rule-{{ $rule->id }}">
                <td>
                    <p class="font-medium">{{ $rule->rule_name }}</p>
                    @if ($rule->description)<p class="text-xs text-slate-500">{{ $rule->description }}</p>@endif
                </td>
                <td>{{ __('admin.rules.actions.'.$rule->action) }}</td>
                <td>{{ $rule->weight }}</td>
                <td>{{ $rule->priority }}</td>
                <td>
                    <button type="button" wire:click="toggle({{ $rule->id }})" aria-label="{{ __('admin.rules.toggle') }}">
                        <x-ui.badge :color="$rule->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                                    :label="$rule->is_active ? __('admin.rules.active') : __('admin.rules.inactive')" />
                    </button>
                </td>
                <td>
                    <div class="flex gap-2">
                        <x-ui.button variant="secondary" wire:click="edit({{ $rule->id }})">{{ __('admin.edit') }}</x-ui.button>
                        <x-ui.button variant="danger" wire:click="delete({{ $rule->id }})" wire:confirm="{{ __('admin.delete_confirm') }}">{{ __('admin.delete') }}</x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state /></td></tr>
        @endforelse
    </x-ui.table>
</div>
