<div class="space-y-6">
    <x-console.flash :message="$flash" />

    @if ($newClient)
        <x-console.secret :title="__('admin.integrations.new_client_title')" :hint="__('admin.shown_once')">
            <p>key_id: <span data-testid="new-key-id">{{ $newClient['key_id'] }}</span></p>
            <p>secret: <span data-testid="new-secret">{{ $newClient['secret'] }}</span></p>
            <div class="pt-2"><x-ui.button variant="secondary" wire:click="dismissSecrets">{{ __('admin.dismiss') }}</x-ui.button></div>
        </x-console.secret>
    @endif

    @if ($newToken)
        <x-console.secret :title="__('admin.integrations.new_token_title')" :hint="__('admin.shown_once')">
            <p data-testid="new-token">{{ $newToken }}</p>
            <div class="pt-2"><x-ui.button variant="secondary" wire:click="dismissSecrets">{{ __('admin.dismiss') }}</x-ui.button></div>
        </x-console.secret>
    @endif

    <x-ui.card :title="__('admin.integrations.api_clients')">
        <form wire:submit="createClient" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1"><x-ui.input name="clientName" wire:model="clientName" :label="__('admin.integrations.client_name')" /></div>
            <x-ui.button type="submit">{{ __('admin.integrations.create_client') }}</x-ui.button>
        </form>

        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.integrations.client_name') }}</th><th>key_id</th><th>{{ __('common.status') }}</th><th>{{ __('admin.integrations.last_used') }}</th><th>{{ __('common.actions') }}</th>
            </x-slot:head>
            @forelse ($clients as $client)
                <tr wire:key="client-{{ $client->id }}">
                    <td>{{ $client->name }}</td>
                    <td class="font-mono" dir="ltr">{{ $client->key_id }}</td>
                    <td>
                        @if ($client->is_active)
                            <x-ui.badge color="bg-emerald-100 text-emerald-800" :label="__('admin.integrations.active')" />
                        @else
                            <x-ui.badge color="bg-red-100 text-red-800" :label="__('admin.integrations.revoked_label')" />
                        @endif
                    </td>
                    <td dir="ltr">{{ $client->last_used_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        @if ($client->is_active)
                            <x-ui.button variant="danger" wire:click="revokeClient({{ $client->id }})" wire:confirm="{{ __('admin.integrations.revoke_confirm') }}">{{ __('admin.integrations.revoke') }}</x-ui.button>
                        @else
                            <x-ui.button variant="secondary" wire:click="activateClient({{ $client->id }})">{{ __('admin.integrations.activate') }}</x-ui.button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-ui.empty-state /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    <x-ui.card :title="__('admin.integrations.mcp_tokens')">
        <form wire:submit="createToken" class="mb-4 grid gap-3 sm:grid-cols-4 sm:items-start">
            <x-ui.input name="tokenName" wire:model="tokenName" :label="__('admin.integrations.token_name')" />
            <x-console.select name="tokenUserId" wire:model="tokenUserId" :label="__('admin.integrations.token_user')">
                <option value="">—</option>
                @foreach ($staff as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
            </x-console.select>
            <x-console.select name="tokenLevel" wire:model="tokenLevel" :label="__('admin.integrations.token_level')">
                <option value="read">{{ __('admin.integrations.level_read') }}</option>
                <option value="propose">{{ __('admin.integrations.level_propose') }}</option>
            </x-console.select>
            <div class="sm:pt-6"><x-ui.button type="submit">{{ __('admin.integrations.create_token') }}</x-ui.button></div>
        </form>

        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.integrations.token_name') }}</th><th>{{ __('admin.integrations.token_user') }}</th><th>{{ __('admin.integrations.abilities') }}</th><th>{{ __('admin.integrations.last_used') }}</th><th>{{ __('common.actions') }}</th>
            </x-slot:head>
            @forelse ($tokens as $token)
                <tr wire:key="token-{{ $token->id }}">
                    <td>{{ $token->name }}</td>
                    <td>{{ $token->tokenable?->name }}</td>
                    <td class="font-mono text-xs" dir="ltr">{{ implode(', ', (array) $token->abilities) }}</td>
                    <td dir="ltr">{{ $token->last_used_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td><x-ui.button variant="danger" wire:click="revokeToken({{ $token->id }})" wire:confirm="{{ __('admin.integrations.revoke_confirm') }}">{{ __('admin.integrations.revoke') }}</x-ui.button></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-ui.empty-state /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
</div>
