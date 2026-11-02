<?php

namespace App\Support;

/**
 * Weighted "final score" used to compare model runs (project appendix weights):
 * PR-AUC 30, Recall@Precision90 20, Precision@K 15, Interpretability 10, p95 latency 10, Stability 10, Resource cost 5.
 * F1 / F2 / ROC-AUC are displayed but not weighted.
 * Only metrics that are present contribute; the score is normalised to 0-100 over the present weights.
 */
class ModelScore
{
    /**
     * metric => weight (sums to 100). Single source of truth for every weight in this class.
     * L8: these weights are a partial interpretation and await review against the original appendix table; do not change before that review.
     */
    public const WEIGHTS = [
        'pr_auc' => 30,
        'recall_at_p90' => 20,
        'precision_at_k' => 15,
        'interpretability' => 10,
        'p95_latency_ms' => 10,
        'stability' => 10,
        'resource_score' => 5,
    ];

    /** Metrics shown next to the weighted ones but excluded from the final score. */
    public const DISPLAY_ONLY = ['f1', 'f2', 'roc_auc'];

    private const ALIASES = [
        'roc_auc' => ['auc'],
        'recall_at_p90' => ['recall'],
        'precision_at_k' => ['precision'],
        'p95_latency_ms' => ['latency_p95_ms', 'p95_latency'],
        'resource_score' => ['resource_cost'],
    ];

    public const LATENCY_BUDGET_MS = 300;

    /**
     * @return list<string> every canonical metric key (weighted first, then display-only)
     */
    public static function allKeys(): array
    {
        return [...array_keys(self::WEIGHTS), ...self::DISPLAY_ONLY];
    }

    /**
     * @param  array<string, mixed>|null  $metrics
     * @return array<string, float|null> canonical metrics (null when missing)
     */
    public static function normalise(?array $metrics): array
    {
        $metrics ??= [];
        $out = [];

        foreach (self::allKeys() as $key) {
            $value = $metrics[$key] ?? null;
            foreach (self::ALIASES[$key] ?? [] as $alias) {
                $value ??= $metrics[$alias] ?? null;
            }
            $out[$key] = is_numeric($value) ? (float) $value : null;
        }

        // Stability may be derived from the PR-AUC standard deviation across seeds.
        if ($out['stability'] === null && is_numeric($metrics['pr_auc_std'] ?? null)) {
            $out['stability'] = max(0.0, min(1.0, 1 - (float) $metrics['pr_auc_std']));
        }

        return $out;
    }

    /**
     * Models whose p95 latency exceeds the real-time budget are excluded from the real-time path.
     *
     * @param  array<string, mixed>|null  $metrics
     */
    public static function excludedFromRealtime(?array $metrics): bool
    {
        $latency = self::normalise($metrics)['p95_latency_ms'];

        return $latency !== null && $latency > self::LATENCY_BUDGET_MS;
    }

    /**
     * @param  array<string, mixed>|null  $metrics
     */
    public static function final(?array $metrics): ?float
    {
        $metrics = self::normalise($metrics);
        $sum = 0.0;
        $weights = 0;

        foreach (self::WEIGHTS as $key => $weight) {
            $value = $metrics[$key];
            if ($value === null) {
                continue;
            }
            $unit = $key === 'p95_latency_ms'
                ? max(0.0, 1 - $value / self::LATENCY_BUDGET_MS)
                : min(1.0, max(0.0, $value));
            $sum += $unit * $weight;
            $weights += $weight;
        }

        return $weights === 0 ? null : round($sum / $weights * 100, 2);
    }
}
