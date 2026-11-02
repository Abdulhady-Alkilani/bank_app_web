<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('api-client:create {name : Display name of the integration (e.g. "Core Banking", "Simulator")}')]
#[Description('Create an external API client and print its key id and secret (shown once)')]
class CreateApiClient extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $secret = bin2hex(random_bytes(24));

        $client = ApiClient::create([
            'name' => $this->argument('name'),
            'key_id' => 'key_'.Str::random(16),
            'secret' => $secret,
            'is_active' => true,
        ]);

        $audit->log('api_client.created', $client, [], ['name' => $client->name, 'key_id' => $client->key_id]);

        $this->info('API client created. Store the secret now — it is not shown again.');
        $this->line('key_id: '.$client->key_id);
        $this->line('secret: '.$secret);

        return self::SUCCESS;
    }
}
