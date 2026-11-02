<?php

use App\Livewire\Admin\Backups;
use App\Livewire\Admin\Integrations;
use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('creates an api client whose secret is shown once and stored encrypted', function () {
    $component = Livewire::actingAs($this->admin)->test(Integrations::class)
        ->set('clientName', 'Demo Bank')
        ->call('createClient')
        ->assertHasNoErrors();

    $credentials = $component->get('newClient');
    $client = ApiClient::first();

    expect($client->key_id)->toBe($credentials['key_id'])
        ->and($client->secret)->toBe($credentials['secret'])
        ->and(DB::table('api_clients')->value('secret'))->not->toBe($credentials['secret'])
        ->and(json_encode(AuditLog::where('action', 'api_client.created')->first()->new_values))->not->toContain($credentials['secret']);

    // Shown once: the next interaction clears it.
    $component->call('dismissSecrets')->assertSet('newClient', null);
    $component->assertDontSee($credentials['secret']);
});

it('revokes and re-activates api clients', function () {
    $client = ApiClient::factory()->create();

    $component = Livewire::actingAs($this->admin)->test(Integrations::class)->call('revokeClient', $client->id);
    expect($client->fresh()->is_active)->toBeFalse()->and($client->fresh()->revoked_at)->not->toBeNull();

    $component->call('activateClient', $client->id);
    expect($client->fresh()->is_active)->toBeTrue()->and($client->fresh()->revoked_at)->toBeNull();
});

it('issues MCP tokens with the correct abilities and revokes them', function () {
    $analyst = User::factory()->analyst()->create();

    $component = Livewire::actingAs($this->admin)->test(Integrations::class)
        ->set('tokenName', 'Claude read')
        ->set('tokenUserId', (string) $analyst->id)
        ->set('tokenLevel', 'read')
        ->call('createToken')
        ->assertHasNoErrors();

    expect($component->get('newToken'))->toContain('|');
    expect(PersonalAccessToken::first()->abilities)->toBe(['mcp:read']);

    $component->set('tokenName', 'Claude propose')->set('tokenLevel', 'propose')->call('createToken');
    expect(PersonalAccessToken::latest('id')->first()->abilities)->toBe(['mcp:read', 'mcp:propose']);

    $component->call('revokeToken', PersonalAccessToken::first()->id);
    expect(PersonalAccessToken::count())->toBe(1);
    expect(AuditLog::where('action', 'like', 'mcp_token.%')->count())->toBe(3);
});

it('does not issue MCP tokens to customers', function () {
    $customer = User::factory()->customer()->create();

    Livewire::actingAs($this->admin)->test(Integrations::class)
        ->set('tokenName', 'x')->set('tokenUserId', (string) $customer->id)
        ->call('createToken')
        ->assertStatus(422);

    expect(PersonalAccessToken::count())->toBe(0);
});

it('creates a backup file with the artisan command', function () {
    Storage::fake('local');
    User::factory()->count(2)->create();

    $this->artisan('bank:backup')->assertSuccessful();

    $files = Storage::disk('local')->files('backups');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('.zip');

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($files[0]));
    expect($zip->locateName('manifest.json'))->not->toBeFalse()
        ->and(json_decode($zip->getFromName('tables/users.json'), true))->toHaveCount(3);
    $zip->close();

    expect(AuditLog::where('action', 'backup.created')->exists())->toBeTrue();
});

it('restores a JSON backup with --force', function () {
    Storage::fake('local');
    User::factory()->count(2)->create();
    $this->artisan('bank:backup')->assertSuccessful();
    $file = basename(Storage::disk('local')->files('backups')[0]);

    User::factory()->count(3)->create();
    expect(User::count())->toBe(6);

    $this->artisan('bank:backup:restore', ['file' => $file, '--force' => true])->assertSuccessful();
    expect(User::count())->toBe(3);
});

it('lists backups and triggers one from the UI', function () {
    Storage::fake('local');

    $component = Livewire::actingAs($this->admin)->test(Backups::class)->call('create');
    expect(Storage::disk('local')->files('backups'))->toHaveCount(1);

    $file = basename(Storage::disk('local')->files('backups')[0]);
    $component->assertSee($file)->call('download', $file)->assertFileDownloaded($file);
});
