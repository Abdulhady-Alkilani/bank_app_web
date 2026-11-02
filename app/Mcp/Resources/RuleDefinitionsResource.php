<?php

namespace App\Mcp\Resources;

use App\Models\Rule;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Name('rule-definitions')]
#[Title('Rule definitions')]
#[Uri('fraud://rules')]
#[MimeType('application/json')]
#[Description('The currently active detection rules, read live from the rules table: name, description, weight, priority, action and JSON conditions. تعريفات القواعد الفعالة.')]
class RuleDefinitionsResource extends FraudResource
{
    protected function render(): string
    {
        $rules = Rule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (Rule $rule) => [
                'id' => $rule->id,
                'name' => $rule->rule_name,
                'description' => $rule->description,
                'weight' => $rule->weight,
                'priority' => $rule->priority,
                'action' => $rule->action,
                'conditions' => $rule->conditions,
            ])
            ->values()
            ->all();

        return (string) json_encode([
            'count' => count($rules),
            'condition_format' => 'all/any lists of {feature, op, value}; ops: > >= < <= == != in; optional event_types filter',
            'rules' => $rules,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
