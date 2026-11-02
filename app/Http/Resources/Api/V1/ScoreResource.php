<?php

namespace App\Http\Resources\Api\V1;

use App\Data\ScoringResult;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ScoringResult $resource
 */
class ScoreResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        return [
            'subject_type' => $r->subject instanceof Transaction ? 'transaction' : 'event',
            'subject_id' => $r->subject->getKey(),
            'risk_score_id' => $r->riskScore->id,
            'score' => $r->score,
            'decision' => $r->decision->value,
            'reasons' => $r->reasons,
            'otp_challenge_id' => $r->otpChallengeId,
            'latency_ms' => $r->riskScore->latency_ms,
        ];
    }
}
