<?php

namespace App\Http\Resources;

use App\Models\Alert;
use App\Services\Investigation\AlertInsights;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $insights = app(AlertInsights::class);
        $score = $this->riskScore;
        $transaction = $score?->transaction;
        $account = $insights->account($this->resource);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'score' => $score?->score,
            'system_decision' => $score?->decision?->value,
            'analyst_decision' => $this->decision?->value,
            'decision_reason' => $this->decision_reason,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'assigned_to' => $this->assigned_to,
            'case_id' => $this->case_id,
            'account' => $account ? ['id' => $account->id, 'account_number' => $account->account_number, 'status' => $account->status->value] : null,
            'transaction' => $transaction ? [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'channel' => $transaction->channel,
                'status' => $transaction->status->value,
                'time' => $transaction->transaction_time?->toIso8601String(),
            ] : null,
            'reasons' => $this->when($request->routeIs('api.v1.alerts.show') || $request->routeIs('api.v1.alerts.decision'), fn () => $insights->reasons($score)),
            'features' => $this->when($request->routeIs('api.v1.alerts.show') || $request->routeIs('api.v1.alerts.decision'), fn () => $score?->features ?? []),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
