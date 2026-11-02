<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\FraudPresenter;
use App\Mcp\Support\McpAccess;
use App\Models\RiskScore;
use App\Models\User;
use App\Services\Graph\GraphService;
use App\Support\Masker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('find_related_accounts')]
#[Title('Find related accounts')]
#[Description('Read-only. Link analysis: accounts connected to the given account through shared devices, IPs or beneficiaries within depth hops (1-3, default 2). Useful for mule rings and account-takeover clusters. الحسابات المرتبطة.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class FindRelatedAccountsTool extends FraudTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'alert_id' => $schema->integer()->description('Resolve the account from this alert'),
            'account_id' => $schema->integer()->description('Account id'),
            'account_number' => $schema->string()->description('Full account number (masked in the output)'),
            'depth' => $schema->integer()->description('Number of hops to explore (1-3, default 2)')->min(1)->max(3)->default(2),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $data = $request->validate([
            'alert_id' => 'nullable|integer|min:1',
            'account_id' => 'nullable|integer|min:1',
            'account_number' => 'nullable|string|max:64',
            'depth' => 'nullable|integer|min:1|max:3',
        ]);

        $account = McpAccess::accountFor($user, $request->all());
        $depth = (int) ($data['depth'] ?? 2);
        $graph = app(GraphService::class);

        $related = $graph->relatedAccounts($account, $depth)->load('customer');
        $network = $graph->networkForAccount($account, $depth);

        $nodeTypes = collect($network['nodes'])->map(fn ($node) => $node['data']['type'] ?? 'unknown')->countBy()->all();

        $maxScores = RiskScore::query()
            ->selectRaw('transactions.account_id as account_id, max(risk_scores.score) as max_score')
            ->join('transactions', 'transactions.id', '=', 'risk_scores.transaction_id')
            ->whereIn('transactions.account_id', $related->pluck('id'))
            ->groupBy('transactions.account_id')
            ->pluck('max_score', 'account_id');

        return [
            'account' => FraudPresenter::account($account),
            'depth' => $depth,
            'related_count' => $related->count(),
            'related_accounts' => $related->map(fn ($other) => [
                'id' => $other->id,
                'account_number' => Masker::maskAccount($other->account_number),
                'status' => $other->status->value,
                'customer_name' => Masker::maskName($other->customer?->name),
                'max_risk_score' => isset($maxScores[$other->id]) ? (int) $maxScores[$other->id] : null,
            ])->values()->all(),
            'network_summary' => [
                'nodes' => count($network['nodes']),
                'edges' => count($network['edges']),
                'nodes_by_type' => $nodeTypes,
            ],
        ];
    }
}
