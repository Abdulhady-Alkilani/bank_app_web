<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\FraudPresenter;
use App\Mcp\Support\McpAccess;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_transaction_history')]
#[Title('Get transaction history')]
#[Description('Read-only. Returns the most recent transactions of an account (newest first) with masked beneficiaries and IPs and the risk score of each. Supports limit (max 100) and days (max 365). سجل المعاملات.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetTransactionHistoryTool extends FraudTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'alert_id' => $schema->integer()->description('Resolve the account from this alert'),
            'account_id' => $schema->integer()->description('Account id'),
            'account_number' => $schema->string()->description('Full account number (masked in the output)'),
            'limit' => $schema->integer()->description('Maximum rows (default 20, max 100)')->min(1)->max(100)->default(20),
            'days' => $schema->integer()->description('Look-back window in days (default 30, max 365)')->min(1)->max(365)->default(30),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $data = $request->validate([
            'alert_id' => 'nullable|integer|min:1',
            'account_id' => 'nullable|integer|min:1',
            'account_number' => 'nullable|string|max:64',
            'limit' => 'nullable|integer|min:1|max:100',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $account = McpAccess::accountFor($user, $request->all());
        $limit = (int) ($data['limit'] ?? 20);
        $days = (int) ($data['days'] ?? 30);

        $transactions = Transaction::query()
            ->with(['beneficiary', 'riskScore'])
            ->where('account_id', $account->id)
            ->where('transaction_time', '>=', now()->subDays($days))
            ->orderByDesc('transaction_time')
            ->limit($limit)
            ->get();

        return [
            'account' => FraudPresenter::account($account),
            'window_days' => $days,
            'count' => $transactions->count(),
            'total_amount' => round((float) $transactions->sum('amount'), 2),
            'transactions' => $transactions->map(fn (Transaction $t) => FraudPresenter::transaction($t))->all(),
        ];
    }
}
