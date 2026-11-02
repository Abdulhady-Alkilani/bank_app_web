<?php

namespace App\Livewire\Analyst;

use App\Enums\EventType;
use App\Enums\RuleSuggestionStatus;
use App\Models\RuleSuggestion;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RuleSuggestions extends Component
{
    public const FEATURES = [
        'amount', 'amount_to_avg_ratio', 'is_new_device', 'device_account_count', 'logins_new_device_1h',
        'minutes_since_phone_change', 'minutes_since_beneficiary_added', 'beneficiary_is_new',
        'beneficiary_account_age_days', 'beneficiary_fan_out_1h', 'txn_count_1h', 'location_changed',
        'hour_of_day', 'limit_raised_recently',
    ];

    public const OPERATORS = ['>', '>=', '<', '<=', '==', '!=', 'in'];

    public string $title = '';

    public string $description = '';

    public string $match = 'all';

    /** @var array<int, string> */
    public array $eventTypes = [];

    /** @var array<int, array{feature: string, op: string, value: string}> */
    public array $conditions = [];

    public function mount(): void
    {
        Gate::authorize('rule_suggestions.create');

        $this->conditions = [$this->blankRow()];
    }

    public function addCondition(): void
    {
        $this->conditions[] = $this->blankRow();
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);

        if ($this->conditions === []) {
            $this->conditions = [$this->blankRow()];
        }
    }

    public function submit(AuditLogger $audit): void
    {
        Gate::authorize('rule_suggestions.create');

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'match' => ['required', Rule::in(['all', 'any'])],
            'eventTypes' => ['array'],
            'eventTypes.*' => [Rule::in(EventType::values())],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.feature' => ['required', Rule::in(self::FEATURES)],
            'conditions.*.op' => ['required', Rule::in(self::OPERATORS)],
            'conditions.*.value' => ['required', 'string', 'max:255'],
        ], attributes: [
            'title' => __('analyst.rules.title_label'),
            'conditions.*.value' => __('analyst.rules.value'),
        ]);

        $json = [$data['match'] => array_map(fn (array $row) => [
            'feature' => $row['feature'],
            'op' => $row['op'],
            'value' => $this->castValue($row['op'], $row['value']),
        ], $data['conditions'])];

        if (! empty($data['eventTypes'])) {
            $json = ['event_types' => array_values($data['eventTypes'])] + $json;
        }

        $suggestion = RuleSuggestion::create([
            'suggested_by' => auth()->id(),
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'conditions' => $json,
            'status' => RuleSuggestionStatus::Pending,
        ]);

        $audit->log('rule_suggestion.created', $suggestion, [], ['title' => $suggestion->title]);

        $this->reset(['title', 'description', 'match', 'eventTypes']);
        $this->conditions = [$this->blankRow()];
        session()->now('success', __('analyst.rules.saved'));
    }

    public function render(): View
    {
        return view('livewire.analyst.rule-suggestions', [
            'features' => self::FEATURES,
            'operators' => self::OPERATORS,
            'eventTypeOptions' => EventType::options(),
            'suggestions' => RuleSuggestion::query()->where('suggested_by', auth()->id())->latest()->limit(30)->get(),
        ])->title(__('analyst.rules.title'));
    }

    /**
     * @return array{feature: string, op: string, value: string}
     */
    private function blankRow(): array
    {
        return ['feature' => self::FEATURES[0], 'op' => '>=', 'value' => ''];
    }

    private function castValue(string $op, string $value): mixed
    {
        $cast = function (string $v): mixed {
            $v = trim($v);

            return match (true) {
                is_numeric($v) => $v + 0,
                strtolower($v) === 'true' => true,
                strtolower($v) === 'false' => false,
                default => $v,
            };
        };

        return $op === 'in' ? array_map($cast, array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== '')) : $cast($value);
    }
}
