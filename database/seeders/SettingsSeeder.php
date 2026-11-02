<?php

namespace Database\Seeders;

use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * @var array<string, array{0: mixed, 1: string}>
     */
    public const DEFAULTS = [
        'decision.allow_max' => [39, 'Maximum score for an automatic allow'],
        'decision.challenge_max' => [74, 'Maximum score for an OTP challenge (above it: block)'],
        'scoring.rule_weight' => [0.4, 'Weight of the rules score in the final score'],
        'scoring.model_weight' => [0.6, 'Weight of the model probability in the final score'],
        'otp.ttl_seconds' => [300, 'OTP validity in seconds'],
        'otp.max_attempts' => [3, 'Maximum OTP attempts'],
        'transfer.default_limit' => [20000, 'Default transfer limit per account'],
        'cases.high_value_threshold' => [50000, 'Case value that requires supervisor approval to close'],
        'qa.sample_rate' => [0.1, 'Share of closed alerts sampled for quality review'],
        'alerts.analyst_daily_capacity' => [50, 'Maximum alerts assigned per analyst per day'],
        'security.require_staff_2fa' => [false, 'Force staff to enable two-factor authentication'],
    ];

    public function run(): void
    {
        $settings = app(SettingsService::class);

        foreach (self::DEFAULTS as $key => [$value, $description]) {
            $settings->set($key, $value, $description);
        }
    }
}
