<?php

namespace App\Jobs;

use App\Data\AiScoreResult;
use App\Models\RiskScore;
use App\Services\Scoring\ModelRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shadow mode (L4): asks the AI service's shadow model to score the same features and stores the result
 * next to the stored risk score. It never changes the decision; any failure (404, timeout, bad payload) is ignored.
 */
class ShadowScoreRiskScore implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $features
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly int $riskScoreId,
        public readonly array $features,
        public readonly array $context,
    ) {}

    public function handle(ModelRegistry $registry): void
    {
        $riskScore = RiskScore::find($this->riskScoreId);
        if (! $riskScore) {
            return;
        }

        try {
            $request = Http::baseUrl(rtrim((string) config('fraud.ai.url'), '/'))
                ->acceptJson()
                ->asJson()
                ->connectTimeout((float) config('fraud.ai.shadow_timeout_seconds', 2))
                ->timeout((float) config('fraud.ai.shadow_timeout_seconds', 2));

            if ($token = config('fraud.ai.token')) {
                $request = $request->withToken($token);
            }

            $response = $request->post('/v1/shadow/score', ['features' => $this->features, 'context' => $this->context]);
        } catch (Throwable $e) {
            Log::debug('Shadow scoring unreachable', ['risk_score_id' => $this->riskScoreId, 'error' => $e->getMessage()]);

            return;
        }

        $probability = $response->json('probability');
        if (! $response->successful() || ! is_numeric($probability)) {
            Log::debug('Shadow scoring ignored', ['risk_score_id' => $this->riskScoreId, 'status' => $response->status()]);

            return;
        }

        $run = $registry->runFor(new AiScoreResult(
            probability: (float) $probability,
            modelName: $response->json('model_name'),
            modelVersion: $response->json('model_version'),
        ));

        $riskScore->update([
            'shadow_probability' => max(0.0, min(1.0, (float) $probability)),
            'shadow_model_run_id' => $run?->id,
        ]);
    }
}
