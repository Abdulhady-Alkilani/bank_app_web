<?php

namespace App\Services\Scoring;

use App\Data\AiScoreResult;
use App\Data\DecisionResult;
use App\Data\RulesResult;
use App\Enums\Decision;
use App\Services\Settings\SettingsService;

/**
 * Combines the rules score and the model probability into the final 0..100 score and decision.
 *
 *  score = (rule_w * rule_score + model_w * probability * 100) / (rule_w + model_w)
 *  - AI failed / disabled  => score = rule_score, used_fallback = true
 *  - blacklist hit or frozen account => 100 (block)
 *  - whitelist hit (and no blacklist) => score is cut by WHITELIST_REDUCTION
 *  - score <= decision.allow_max => allow; <= decision.challenge_max => challenge; else block
 */
class DecisionEngine
{
    public const WHITELIST_REDUCTION = 50;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  array<string, mixed>  $features
     */
    public function decide(RulesResult $rules, ?AiScoreResult $ai, array $features = []): DecisionResult
    {
        $fallback = $ai === null;
        $ruleScore = $rules->score;

        if ($fallback) {
            $score = (float) $ruleScore;
        } else {
            $rw = max(0.0, (float) $this->settings->get('scoring.rule_weight', 0.4));
            $mw = max(0.0, (float) $this->settings->get('scoring.model_weight', 0.6));
            $total = ($rw + $mw) > 0 ? $rw + $mw : 1.0;
            $score = ($rw * $ruleScore + $mw * $ai->probability * 100) / $total;
        }

        $reasons = $rules->triggered;
        foreach ($ai?->topFeatures ?? [] as $f) {
            $name = (string) ($f['name'] ?? '');
            $reasons[] = [
                'code' => 'model:'.$name,
                'text' => __('scoring.model_reason', [
                    'feature' => __('scoring.features.'.$name, [], 'ar'),
                    'value' => (string) ($f['value'] ?? '-'),
                ], 'ar'),
                'text_en' => __('scoring.model_reason', [
                    'feature' => __('scoring.features.'.$name, [], 'en'),
                    'value' => (string) ($f['value'] ?? '-'),
                ], 'en'),
                'weight' => round((float) ($f['impact'] ?? 0), 3),
                'source' => 'model',
            ];
        }

        if ($fallback) {
            $reasons[] = $this->reason('fallback', 'fallback', 0);
        }

        if ($rules->whitelistHit && ! $rules->blacklistHit) {
            $score *= (100 - self::WHITELIST_REDUCTION) / 100;
            $reasons[] = $this->reason('whitelist', 'whitelist', -self::WHITELIST_REDUCTION, ['percent' => self::WHITELIST_REDUCTION]);
        }

        if ($rules->blacklistHit) {
            $score = 100;
            $reasons[] = $this->reason('blacklist', 'blacklist', 100);
        }

        if (! empty($features['account_frozen'])) {
            $score = 100;
            $reasons[] = $this->reason('account_frozen', 'account_frozen', 100);
        }

        $final = (int) max(0, min(100, round($score)));

        return new DecisionResult(
            score: $final,
            decision: $this->decisionFor($final),
            ruleScore: $ruleScore,
            modelProbability: $ai?->probability,
            usedFallback: $fallback,
            reasons: $reasons,
        );
    }

    public function decisionFor(int $score): Decision
    {
        $allowMax = (int) $this->settings->get('decision.allow_max', 39);
        $challengeMax = (int) $this->settings->get('decision.challenge_max', 74);

        return match (true) {
            $score <= $allowMax => Decision::Allow,
            $score <= $challengeMax => Decision::Challenge,
            default => Decision::Block,
        };
    }

    /**
     * @param  array<string, mixed>  $replace
     * @return array{code: string, text: string, text_en: string, weight: int, source: string}
     */
    private function reason(string $code, string $key, int $weight, array $replace = []): array
    {
        return [
            'code' => $code,
            'text' => __('scoring.'.$key, $replace, 'ar'),
            'text_en' => __('scoring.'.$key, $replace, 'en'),
            'weight' => $weight,
            'source' => 'rule',
        ];
    }
}
