<?php

namespace App\Mcp\Resources;

use App\Services\Settings\SettingsService;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Name('fraud-policy')]
#[Title('Fraud policy')]
#[Uri('fraud://policy')]
#[MimeType('text/markdown')]
#[Description('Bank fraud policy: decision thresholds (read live from system settings), scoring weights, the human-in-the-loop rule and data handling. سياسة مكافحة الاحتيال.')]
class FraudPolicyResource extends FraudResource
{
    protected function render(): string
    {
        $settings = app(SettingsService::class);

        $allowMax = (int) $settings->get('decision.allow_max', 39);
        $challengeMax = (int) $settings->get('decision.challenge_max', 74);

        return strtr($this->staticContent('fraud-policy.md'), [
            '{{allow_max}}' => (string) $allowMax,
            '{{challenge_min}}' => (string) ($allowMax + 1),
            '{{challenge_max}}' => (string) $challengeMax,
            '{{block_min}}' => (string) ($challengeMax + 1),
            '{{rule_weight}}' => (string) $settings->get('scoring.rule_weight', 0.4),
            '{{model_weight}}' => (string) $settings->get('scoring.model_weight', 0.6),
            '{{high_value_threshold}}' => (string) $settings->get('cases.high_value_threshold', 50000),
            '{{otp_ttl}}' => (string) $settings->get('otp.ttl_seconds', 300),
            '{{otp_attempts}}' => (string) $settings->get('otp.max_attempts', 3),
        ]);
    }
}
