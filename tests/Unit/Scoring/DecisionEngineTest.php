<?php

use App\Data\AiScoreResult;
use App\Data\RulesResult;
use App\Enums\Decision;
use App\Services\Scoring\DecisionEngine;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    $this->engine = app(DecisionEngine::class);
});

it('blends rule score and model probability with the configured weights', function () {
    $result = $this->engine->decide(new RulesResult(50), new AiScoreResult(0.5));

    expect($result->score)->toBe(50) // 0.4*50 + 0.6*50
        ->and($result->decision)->toBe(Decision::Challenge)
        ->and($result->usedFallback)->toBeFalse()
        ->and($result->modelProbability)->toBe(0.5);
});

it('maps scores to decisions using the settings thresholds', function () {
    expect($this->engine->decisionFor(39))->toBe(Decision::Allow)
        ->and($this->engine->decisionFor(40))->toBe(Decision::Challenge)
        ->and($this->engine->decisionFor(74))->toBe(Decision::Challenge)
        ->and($this->engine->decisionFor(75))->toBe(Decision::Block);

    app(SettingsService::class)->set('decision.allow_max', 10);
    app(SettingsService::class)->set('decision.challenge_max', 20);

    expect($this->engine->decisionFor(15))->toBe(Decision::Challenge)
        ->and($this->engine->decisionFor(21))->toBe(Decision::Block);
});

it('falls back to the rules score when the model is unavailable', function () {
    $result = $this->engine->decide(new RulesResult(80), null);

    expect($result->score)->toBe(80)
        ->and($result->usedFallback)->toBeTrue()
        ->and($result->modelProbability)->toBeNull()
        ->and($result->decision)->toBe(Decision::Block)
        ->and(collect($result->reasons)->pluck('code'))->toContain('fallback');
});

it('forces 100 on a blacklist hit and cuts the score on a whitelist hit', function () {
    $black = $this->engine->decide(new RulesResult(0, [], true, false), new AiScoreResult(0.0));
    expect($black->score)->toBe(100)->and($black->decision)->toBe(Decision::Block);

    $white = $this->engine->decide(new RulesResult(60, [], false, true), new AiScoreResult(0.6));
    expect($white->score)->toBe(30)->and($white->decision)->toBe(Decision::Allow);
});

it('merges rule reasons with model top features', function () {
    $rules = new RulesResult(45, [['code' => 'rule:1', 'rule_id' => 1, 'text' => 'x', 'text_en' => 'x', 'weight' => 45, 'action' => 'flag', 'source' => 'rule']]);
    $ai = new AiScoreResult(0.9, 'm', '1', [['name' => 'is_new_device', 'value' => 1, 'impact' => 1.2]]);

    $reasons = $this->engine->decide($rules, $ai)->reasons;

    expect($reasons)->toHaveCount(2)
        ->and($reasons[1]['source'])->toBe('model')
        ->and($reasons[1]['code'])->toBe('model:is_new_device')
        ->and($reasons[1]['weight'])->toBe(1.2);
});

it('blocks everything on a frozen account', function () {
    $result = $this->engine->decide(new RulesResult(0), new AiScoreResult(0.0), ['account_frozen' => 1]);

    expect($result->score)->toBe(100)->and($result->decision)->toBe(Decision::Block);
});
