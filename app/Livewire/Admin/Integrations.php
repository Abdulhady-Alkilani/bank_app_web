<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\ApiClient;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Component;

class Integrations extends Component
{
    use AuthorizesPermission;

    public string $clientName = '';

    public string $tokenName = '';

    public string $tokenUserId = '';

    /** read | propose */
    public string $tokenLevel = 'read';

    public ?string $flash = null;

    /** @var array{key_id: string, secret: string}|null shown once */
    public ?array $newClient = null;

    public ?string $newToken = null;

    public function mount(): void
    {
        $this->authorizePermission('integrations.manage');
    }

    public function createClient(AuditLogger $audit): void
    {
        $this->authorizePermission('integrations.manage');
        $this->reveal();

        $data = $this->validate(['clientName' => ['required', 'string', 'max:255']]);

        $keyId = 'key_'.Str::lower(Str::random(16));
        $secret = Str::random(48);

        $client = ApiClient::create([
            'name' => $data['clientName'],
            'key_id' => $keyId,
            'secret' => $secret,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $audit->log('api_client.created', $client, [], ['name' => $client->name, 'key_id' => $keyId]);

        $this->newClient = ['key_id' => $keyId, 'secret' => $secret];
        $this->clientName = '';
    }

    public function revokeClient(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('integrations.manage');
        $this->reveal();

        $client = ApiClient::findOrFail($id);
        $client->update(['is_active' => false, 'revoked_at' => now()]);
        $audit->log('api_client.revoked', $client, ['is_active' => true], ['is_active' => false]);
        $this->flash = __('admin.integrations.revoked');
    }

    public function activateClient(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('integrations.manage');
        $this->reveal();

        $client = ApiClient::findOrFail($id);
        $client->update(['is_active' => true, 'revoked_at' => null]);
        $audit->log('api_client.activated', $client, ['is_active' => false], ['is_active' => true]);
        $this->flash = __('admin.integrations.activated');
    }

    public function createToken(AuditLogger $audit): void
    {
        $this->authorizePermission('integrations.manage');
        $this->reveal();

        $data = $this->validate([
            'tokenName' => ['required', 'string', 'max:100'],
            'tokenUserId' => ['required', 'exists:users,id'],
            'tokenLevel' => ['required', 'in:read,propose'],
        ]);

        $user = User::findOrFail($data['tokenUserId']);
        abort_unless($user->isStaff() && $user->is_active, 422, __('admin.integrations.staff_only'));

        $abilities = $data['tokenLevel'] === 'propose' ? ['mcp:read', 'mcp:propose'] : ['mcp:read'];
        $token = $user->createToken($data['tokenName'], $abilities);

        $audit->log('mcp_token.created', $user, [], ['token_name' => $data['tokenName'], 'abilities' => $abilities, 'token_id' => $token->accessToken->id]);

        $this->newToken = $token->plainTextToken;
        $this->tokenName = '';
    }

    public function revokeToken(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('integrations.manage');
        $this->reveal();

        $token = PersonalAccessToken::findOrFail($id);
        $audit->log('mcp_token.revoked', $token->tokenable, ['token_name' => $token->name, 'abilities' => $token->abilities], []);
        $token->delete();
        $this->flash = __('admin.integrations.token_revoked');
    }

    public function dismissSecrets(): void
    {
        $this->reveal();
    }

    private function reveal(): void
    {
        $this->newClient = null;
        $this->newToken = null;
        $this->flash = null;
    }

    public function render(): View
    {
        $tokens = PersonalAccessToken::query()->with('tokenable')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->latest('id')->get()
            ->filter(fn (PersonalAccessToken $t) => in_array('mcp:read', (array) $t->abilities, true))
            ->values();

        $staff = User::query()->where('is_active', true)->with('roles')->orderBy('name')->get()
            ->filter(fn (User $u) => $u->isStaff())->values();

        return view('livewire.admin.integrations', [
            'clients' => ApiClient::latest('id')->get(),
            'tokens' => $tokens,
            'staff' => $staff,
        ])->title(__('admin.integrations.title'));
    }
}
