<?php

namespace App\Services\Scoring;

use App\Data\RulesResult;
use App\Enums\EventType;
use App\Models\Rule;
use Illuminate\Support\Collection;

/**
 * Evaluates active rules (ascending priority) against a feature vector.
 *
 * DSL: {"event_types":["transfer"], "all":[{feature,op,value}...], "any":[...]}
 *  - event_types (optional): the rule only applies to those event types.
 *  - all: every condition must hold; any: at least one must hold. If both are given both must pass.
 *  - ops: > >= < <= == != in. A null/missing feature never satisfies a condition.
 *
 * rule_score = min(100, sum of triggered weights). Blacklist/whitelist hits are reported in the result;
 * the DecisionEngine applies them (blacklist => 100, whitelist => score reduced).
 */
class RulesEngine
{
    /**
     * @param  array<string, mixed>  $features
     * @param  Collection<int, Rule>|null  $rules  override the active rules (tests)
     */
    public function evaluate(EventType $eventType, array $features, ?Collection $rules = null): RulesResult
    {
        $rules ??= Rule::query()->where('is_active', true)->orderBy('priority')->orderBy('id')->get();

        $triggered = [];
        $sum = 0;

        foreach ($rules as $rule) {
            $matched = $this->matches((array) $rule->conditions, $eventType, $features);
            if ($matched === null) {
                continue;
            }

            $sum += (int) $rule->weight;
            $triggered[] = [
                'code' => 'rule:'.$rule->id,
                'rule_id' => $rule->id,
                'text' => $this->describe($rule, $matched, $features, 'ar'),
                'text_en' => $this->describe($rule, $matched, $features, 'en'),
                'weight' => (int) $rule->weight,
                'action' => (string) $rule->action,
                'source' => 'rule',
            ];
        }

        return new RulesResult(
            score: min(100, $sum),
            triggered: $triggered,
            blacklistHit: ! empty($features['blacklist_hit']),
            whitelistHit: ! empty($features['whitelist_hit']),
        );
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $features
     * @return array<int, array<string, mixed>>|null the matched conditions, or null when the rule does not fire
     */
    public function matches(array $conditions, EventType $eventType, array $features): ?array
    {
        $types = (array) ($conditions['event_types'] ?? []);
        if ($types !== [] && ! in_array($eventType->value, $types, true)) {
            return null;
        }

        $all = (array) ($conditions['all'] ?? []);
        $any = (array) ($conditions['any'] ?? []);
        if ($all === [] && $any === []) {
            return null;
        }

        foreach ($all as $c) {
            if (! $this->check($c, $features)) {
                return null;
            }
        }

        $matched = $all;
        if ($any !== []) {
            $hit = array_values(array_filter($any, fn ($c) => $this->check($c, $features)));
            if ($hit === []) {
                return null;
            }
            $matched = array_merge($matched, $hit);
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $features
     */
    public function check(array $condition, array $features): bool
    {
        $actual = $features[$condition['feature'] ?? ''] ?? null;
        if ($actual === null) {
            return false;
        }

        $expected = $condition['value'] ?? null;

        return match ($condition['op'] ?? null) {
            '>' => is_numeric($actual) && $actual > $expected,
            '>=' => is_numeric($actual) && $actual >= $expected,
            '<' => is_numeric($actual) && $actual < $expected,
            '<=' => is_numeric($actual) && $actual <= $expected,
            '==' => $this->equals($actual, $expected),
            '!=' => ! $this->equals($actual, $expected),
            'in' => is_array($expected) && collect($expected)->contains(fn ($v) => $this->equals($actual, $v)),
            default => false,
        };
    }

    private function equals(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 1e-9;
        }
        if (is_bool($b)) {
            return (bool) $a === $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * @param  array<int, array<string, mixed>>  $matched
     * @param  array<string, mixed>  $features
     */
    private function describe(Rule $rule, array $matched, array $features, string $locale): string
    {
        $details = collect($matched)->map(function (array $c) use ($features, $locale) {
            $value = $c['value'] ?? null;

            return __('scoring.condition', [
                'feature' => __('scoring.features.'.$c['feature'], [], $locale),
                'op' => __('scoring.ops.'.$c['op'], [], $locale),
                'value' => is_array($value) ? implode(',', $value) : (string) $value,
                'actual' => (string) ($features[$c['feature']] ?? '-'),
            ], $locale);
        })->implode('، ');

        return __('scoring.rule_reason', ['name' => $rule->rule_name, 'details' => $details], $locale);
    }
}
