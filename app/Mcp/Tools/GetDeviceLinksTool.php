<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\FraudPresenter;
use App\Mcp\Support\McpAccess;
use App\Models\User;
use App\Services\Graph\GraphService;
use App\Support\Masker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_device_links')]
#[Title('Get device links')]
#[Description('Read-only. Lists the devices used by the account customer (trusted or not) and which of them are shared with other accounts. الأجهزة المرتبطة والمشتركة.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetDeviceLinksTool extends FraudTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'alert_id' => $schema->integer()->description('Resolve the account from this alert'),
            'account_id' => $schema->integer()->description('Account id'),
            'account_number' => $schema->string()->description('Full account number (masked in the output)'),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $request->validate([
            'alert_id' => 'nullable|integer|min:1',
            'account_id' => 'nullable|integer|min:1',
            'account_number' => 'nullable|string|max:64',
        ]);

        $account = McpAccess::accountFor($user, $request->all());

        $devices = $account->customer
            ? $account->customer->devices()->get()->map(fn ($device) => FraudPresenter::device($device) + [
                'is_trusted' => (bool) $device->pivot->is_trusted,
                'last_used_at' => $device->pivot->last_used_at ? Carbon::parse($device->pivot->last_used_at)->toIso8601String() : null,
            ])->all()
            : [];

        $shared = app(GraphService::class)->sharedDevices($account)->map(fn (array $row) => [
            'device' => FraudPresenter::device($row['device']),
            'shared_with_accounts' => $row['accounts']->map(fn ($other) => [
                'id' => $other->id,
                'account_number' => Masker::maskAccount($other->account_number),
                'status' => $other->status->value,
            ])->values()->all(),
        ])->values()->all();

        return [
            'account' => FraudPresenter::account($account),
            'devices' => $devices,
            'shared_devices' => $shared,
            'shared_device_count' => count($shared),
        ];
    }
}
