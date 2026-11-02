<?php

namespace App\Support;

use App\Enums\EventType;

/**
 * Builds and validates the JSON condition contract used by the rules engine
 * ({"event_types":[...], "all"|"any":[{"feature","op","value"}]}).
 */
class RuleConditions
{
    public const FEATURES = [
        'amount', 'amount_to_avg_ratio', 'is_new_device', 'device_account_count', 'logins_new_device_1h',
        'minutes_since_phone_change', 'minutes_since_beneficiary_added', 'beneficiary_is_new',
        'beneficiary_account_age_days', 'beneficiary_fan_out_1h', 'txn_count_1h', 'location_changed',
        'hour_of_day', 'limit_raised_recently', 'blacklist_hit',
    ];

    public const OPERATORS = ['>', '>=', '<', '<=', '==', '!=', 'in'];

    public const ACTIONS = ['flag', 'challenge', 'block'];

    /**
     * @param  array<int, array{feature?: mixed, op?: mixed, value?: mixed}>  $rows
     * @param  array<int, string>  $eventTypes
     * @return array<string, mixed>
     */
    public static function build(string $logic, array $rows, array $eventTypes = []): array
    {
        $logic = $logic === 'any' ? 'any' : 'all';
        $built = [];

        foreach ($rows as $row) {
            $op = (string) ($row['op'] ?? '==');
            $value = $row['value'] ?? '';

            if ($op === 'in') {
                $value = array_values(array_map(
                    fn ($item) => self::scalar(trim((string) $item)),
                    array_filter(is_array($value) ? $value : explode(',', (string) $value), fn ($item) => trim((string) $item) !== ''),
                ));
            } else {
                $value = self::scalar(is_string($value) ? trim($value) : $value);
            }

            $built[] = ['feature' => (string) ($row['feature'] ?? ''), 'op' => $op, 'value' => $value];
        }

        $conditions = [];
        if ($eventTypes !== []) {
            $conditions['event_types'] = array_values($eventTypes);
        }
        $conditions[$logic] = $built;

        return $conditions;
    }

    /**
     * @param  array<string, mixed>  $conditions
     * @return array<int, string> human readable errors (empty when valid)
     */
    public static function validate(array $conditions): array
    {
        $errors = [];
        $logic = array_key_exists('all', $conditions) ? 'all' : (array_key_exists('any', $conditions) ? 'any' : null);

        if ($logic === null || ! is_array($conditions[$logic]) || $conditions[$logic] === []) {
            return [__('admin.rules.errors.no_conditions')];
        }

        foreach ((array) ($conditions['event_types'] ?? []) as $eventType) {
            if (EventType::tryFrom((string) $eventType) === null) {
                $errors[] = __('admin.rules.errors.event_type', ['type' => (string) $eventType]);
            }
        }

        foreach ($conditions[$logic] as $index => $row) {
            $n = $index + 1;
            if (! in_array($row['feature'] ?? null, self::FEATURES, true)) {
                $errors[] = __('admin.rules.errors.feature', ['n' => $n]);
            }
            if (! in_array($row['op'] ?? null, self::OPERATORS, true)) {
                $errors[] = __('admin.rules.errors.operator', ['n' => $n]);
            }

            $value = $row['value'] ?? null;
            if (($row['op'] ?? null) === 'in') {
                if (! is_array($value) || $value === []) {
                    $errors[] = __('admin.rules.errors.value_list', ['n' => $n]);
                }
            } elseif ($value === null || $value === '' || is_array($value)) {
                $errors[] = __('admin.rules.errors.value', ['n' => $n]);
            }
        }

        return $errors;
    }

    private static function scalar(mixed $value): mixed
    {
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
