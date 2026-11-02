<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\FraudPresenter;
use App\Mcp\Support\McpAccess;
use App\Models\User;
use App\Services\Investigation\AlertInsights;
use App\Support\Masker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_alert_details')]
#[Title('Get alert details')]
#[Description('Read-only. Returns an alert (status, priority, assignee), its risk score, decision, reasons and features, the flagged transaction or event and a surrounding timeline. Sensitive values are masked. الاطلاع على تفاصيل تنبيه.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
class GetAlertDetailsTool extends FraudTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'alert_id' => $schema->integer()->description('Alert id')->min(1)->required(),
        ];
    }

    protected function execute(Request $request, User $user): array
    {
        $request->validate(['alert_id' => 'required|integer|min:1']);

        $alert = McpAccess::alertFor($user, $request->get('alert_id'));
        $insights = app(AlertInsights::class);
        $score = $alert->riskScore;
        $transaction = $score?->transaction;
        $event = $score?->event;
        $account = $insights->account($alert);

        return [
            'alert' => [
                'id' => $alert->id,
                'status' => $alert->status->value,
                'priority' => $alert->priority->value,
                'assigned_to' => $alert->assignee ? ['id' => $alert->assignee->id, 'name' => Masker::maskName($alert->assignee->name)] : null,
                'case_id' => $alert->case_id,
                'decision' => $alert->decision?->value,
                'created_at' => $alert->created_at?->toIso8601String(),
            ],
            'risk' => FraudPresenter::riskScore($score),
            'reasons' => $insights->reasons($score),
            'features' => $score?->features ?? [],
            'transaction' => $transaction ? FraudPresenter::transaction($transaction) : null,
            'event' => $event ? FraudPresenter::event($event) : null,
            'account' => $account ? FraudPresenter::account($account) + ['customer' => FraudPresenter::customer($account->customer)] : null,
            'timeline' => $insights->timeline($alert)->take(30)->map(fn (array $row) => [
                'time' => $row['time']->toIso8601String(),
                'kind' => $row['kind'],
                'title' => $row['title'],
                'detail' => $this->maskTimelineDetail((string) $row['detail']),
                'status' => $row['status'],
                'score' => $row['score'],
                'current' => $row['current'],
            ])->values()->all(),
        ];
    }

    /**
     * Timeline details may embed an IP address; mask every IPv4 literal.
     */
    private function maskTimelineDetail(string $detail): string
    {
        return preg_replace_callback('/\b\d{1,3}(?:\.\d{1,3}){3}\b/', fn (array $m) => Masker::maskIp($m[0]), $detail) ?? $detail;
    }
}
