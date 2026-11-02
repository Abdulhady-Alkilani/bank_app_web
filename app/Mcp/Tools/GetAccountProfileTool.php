<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\FraudPresenter;
use App\Mcp\Support\McpAccess;
use App\Models\Alert;
use App\Models\User;
use App\Services\Investigation\CustomerInsights;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_account_profile')]
#[Title('Get account profile')]
#[Description('Read-only. Returns a masked account and customer profile, the customer usual behaviour (amounts, hours, cities), beneficiaries and recent account actions. Identify the account by alert_id, account_id or account_number. ملف الحساب والعميل.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetAccountProfileTool extends FraudTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'alert_id' => $schema->integer()->description('Resolve the account from this alert'),
            'account_id' => $schema->integer()->description('Account id'),
            'account_number' => $schema->string()->description('Full account number (will be masked in the output)'),
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
        $customer = $account->customer;

        $profile = [
            'account' => FraudPresenter::account($account),
            'customer' => FraudPresenter::customer($customer),
            'behaviour' => null,
            'beneficiaries' => $account->beneficiaries()->latest('added_at')->limit(15)->get()
                ->map(fn ($b) => FraudPresenter::beneficiary($b))->all(),
            'recent_actions' => $account->actions()->latest()->limit(10)->get()->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action->value,
                'status' => $a->status->value,
                'reason' => $a->reason,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->all(),
            'open_alerts' => Alert::query()
                ->whereHas('riskScore', fn ($q) => $q
                    ->whereHas('transaction', fn ($t) => $t->where('account_id', $account->id))
                    ->orWhereHas('event', fn ($e) => $e->where('account_id', $account->id)))
                ->where('status', '!=', 'closed')
                ->count(),
        ];

        if ($customer) {
            $patterns = app(CustomerInsights::class)->patterns($customer);
            unset($patterns['device_ids']);
            $profile['behaviour'] = $patterns + ['known_devices' => $customer->customerDevices()->count()];
        }

        return $profile;
    }
}
