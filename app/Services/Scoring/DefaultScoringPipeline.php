<?php

namespace App\Services\Scoring;

use App\Contracts\AiScoringClient;
use App\Contracts\ScoringPipeline;
use App\Data\AiScoreResult;
use App\Data\IngestedEvent;
use App\Data\ScoringResult;
use App\Enums\OtpStatus;
use App\Jobs\ShadowScoreRiskScore;
use App\Models\OtpChallenge;
use App\Models\RiskScore;
use App\Services\Ingestion\EventIngestor;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DefaultScoringPipeline implements ScoringPipeline
{
    public function __construct(
        private readonly EventIngestor $ingestor,
        private readonly FeatureService $features,
        private readonly RulesEngine $rules,
        private readonly AiScoringClient $ai,
        private readonly DecisionEngine $decisions,
        private readonly DecisionExecutor $executor,
        private readonly ModelRegistry $registry,
        private readonly SettingsService $settings,
    ) {}

    public function process(array $payload, bool $sync = true): ScoringResult
    {
        $start = hrtime(true);

        $ingested = $this->ingestor->ingest($payload);

        if ($ingested->replayed && ($existing = $ingested->subject->riskScore)) {
            return $this->fromExisting($existing, $ingested);
        }

        $features = $this->features->compute($ingested);
        $rules = $this->rules->evaluate($ingested->eventType, $features);
        $aiResult = $this->callAi($features, $ingested);
        $decision = $this->decisions->decide($rules, $aiResult, $features);

        $transaction = $ingested->transaction();
        $modelRun = $this->registry->runFor($aiResult);
        if ($aiResult && ! $modelRun) {
            Log::warning('AI answer came from an unregistered model version', ['model_name' => $aiResult->modelName, 'model_version' => $aiResult->modelVersion]);
        }

        $riskScore = DB::transaction(fn () => RiskScore::create([
            'transaction_id' => $transaction?->id,
            'event_id' => $ingested->event()?->id,
            'model_run_id' => $modelRun?->id,
            'score' => $decision->score,
            'decision' => $decision->decision,
            'reasons' => $decision->reasons,
            'evaluated_at' => now(),
            'rule_score' => $decision->ruleScore,
            'model_probability' => $decision->modelProbability,
            'used_fallback' => $decision->usedFallback,
            'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
            'features' => $features,
        ]));

        if (config('fraud.ai.shadow_enabled') && config('fraud.ai.driver') === 'http') {
            ShadowScoreRiskScore::dispatch($riskScore->id, $features, $this->aiContext($ingested));
        }

        $challenge = $this->executor->execute($riskScore, $ingested);

        return new ScoringResult(
            riskScore: $riskScore,
            score: $riskScore->score,
            decision: $riskScore->decision,
            reasons: $riskScore->reasons ?? [],
            otpChallengeId: $challenge?->id,
            subject: $ingested->subject->refresh(),
        );
    }

    /**
     * @param  array<string, mixed>  $features
     */
    private function callAi(array $features, IngestedEvent $ingested): ?AiScoreResult
    {
        if (! (bool) $this->settings->get('ai.enabled', true)) {
            return null;
        }

        try {
            return $this->ai->score($features, $this->aiContext($ingested));
        } catch (Throwable $e) {
            Log::warning('AI scoring failed, falling back to rules only', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function aiContext(IngestedEvent $ingested): array
    {
        return [
            'event_type' => $ingested->eventType->value,
            'account_id' => $ingested->account->id,
            'customer_id' => $ingested->customer->id,
            'transaction_id' => $ingested->transaction()?->id,
            'event_id' => $ingested->event()?->id,
            'event_time' => $ingested->eventTime->toIso8601String(),
        ];
    }

    private function fromExisting(RiskScore $riskScore, IngestedEvent $ingested): ScoringResult
    {
        $otp = OtpChallenge::query()
            ->when(
                $ingested->transaction(),
                fn ($q, $tx) => $q->where('transaction_id', $tx->id),
                fn ($q) => $q->where('event_id', $ingested->event()?->id),
            )
            ->where('status', OtpStatus::Pending->value)
            ->latest('id')
            ->first();

        return new ScoringResult(
            riskScore: $riskScore,
            score: $riskScore->score,
            decision: $riskScore->decision,
            reasons: $riskScore->reasons ?? [],
            otpChallengeId: $otp?->id,
            subject: $ingested->subject,
        );
    }
}
