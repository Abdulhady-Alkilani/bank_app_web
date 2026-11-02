<?php

namespace App\Services\Ai;

use App\Contracts\AiScoringClient;
use App\Data\AiScoreResult;
use App\Services\Scoring\ModelRegistry;

/**
 * Deterministic in-process heuristic (logistic regression with hand-set weights).
 * Stands in for the Python service until it exists, answering as the registered champion.
 */
class FakeAiScoringClient implements AiScoringClient
{
    private const BIAS = -4.2;

    public function __construct(private readonly ModelRegistry $registry) {}

    public function score(array $features, array $context): AiScoreResult
    {
        $start = hrtime(true);

        $contributions = $this->contributions($features);
        $z = self::BIAS + array_sum(array_column($contributions, 'impact'));
        $probability = round(1 / (1 + exp(-$z)), 4);

        usort($contributions, fn ($a, $b) => abs($b['impact']) <=> abs($a['impact']));
        $top = array_map(
            fn ($c) => ['name' => $c['name'], 'value' => $c['value'], 'impact' => round($c['impact'], 3)],
            array_slice(array_filter($contributions, fn ($c) => $c['impact'] > 0), 0, 5),
        );

        $champion = $this->registry->championRun();

        return new AiScoreResult(
            probability: $probability,
            modelName: $champion?->model?->model_name ?? 'fake-heuristic',
            modelVersion: $champion?->version_number,
            topFeatures: array_values($top),
            latencyMs: (int) ((hrtime(true) - $start) / 1_000_000),
        );
    }

    public function health(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<int, array{name: string, value: mixed, impact: float}>
     */
    private function contributions(array $f): array
    {
        $out = [];
        $add = function (string $name, float $impact) use (&$out, $f) {
            $out[] = ['name' => $name, 'value' => $f[$name] ?? null, 'impact' => $impact];
        };

        $ratio = (float) ($f['amount_to_avg_ratio'] ?? 0);
        $add('amount_to_avg_ratio', 0.18 * min($ratio, 25));

        $add('is_new_device', ! empty($f['is_new_device']) ? 1.2 : 0.0);

        $phone = $f['minutes_since_phone_change'] ?? null;
        $add('minutes_since_phone_change', $phone === null ? 0.0 : ($phone <= 60 ? 2.2 : ($phone <= 1440 ? 1.0 : 0.0)));

        $age = $f['beneficiary_account_age_days'] ?? null;
        $add('beneficiary_account_age_days', $age === null ? 0.0 : ($age < 7 ? 1.2 : ($age < 30 ? 0.5 : 0.0)));

        $add('device_account_count', min(2.0, max(0, (int) ($f['device_account_count'] ?? 0) - 3) * 0.4));
        $add('beneficiary_fan_out_1h', min(2.0, max(0, (int) ($f['beneficiary_fan_out_1h'] ?? 0) - 1) * 0.5));
        $add('location_changed', ! empty($f['location_changed']) ? 0.9 : 0.0);

        return $out;
    }
}
