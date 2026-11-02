<?php

use App\Enums\EventType;
use App\Models\Rule;
use App\Services\Scoring\RulesEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function dslRule(array $conditions, int $weight = 40, string $name = 'Test rule'): Rule
{
    return new Rule(['rule_name' => $name, 'weight' => $weight, 'priority' => 10, 'is_active' => true, 'conditions' => $conditions, 'action' => 'flag']);
}

it('evaluates all/any conditions and the event type filter', function () {
    $engine = new RulesEngine;
    $features = ['amount_to_avg_ratio' => 12.0, 'minutes_since_phone_change' => 30, 'is_new_device' => 1];

    $all = dslRule(['event_types' => ['transfer'], 'all' => [
        ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 10],
        ['feature' => 'minutes_since_phone_change', 'op' => '<=', 'value' => 60],
    ]]);

    expect($engine->evaluate(EventType::Transfer, $features, collect([$all]))->score)->toBe(40)
        ->and($engine->evaluate(EventType::Login, $features, collect([$all]))->score)->toBe(0);

    $failing = dslRule(['all' => [
        ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 10],
        ['feature' => 'minutes_since_phone_change', 'op' => '<=', 'value' => 10],
    ]]);
    expect($engine->evaluate(EventType::Transfer, $features, collect([$failing]))->score)->toBe(0);

    $any = dslRule(['any' => [
        ['feature' => 'is_new_device', 'op' => '==', 'value' => 0],
        ['feature' => 'amount_to_avg_ratio', 'op' => '>', 'value' => 11],
    ]]);
    expect($engine->evaluate(EventType::Transfer, $features, collect([$any]))->score)->toBe(40);
});

it('supports every operator', function () {
    $engine = new RulesEngine;
    $f = ['n' => 5, 'h' => 3];

    expect($engine->check(['feature' => 'n', 'op' => '>', 'value' => 4], $f))->toBeTrue()
        ->and($engine->check(['feature' => 'n', 'op' => '>=', 'value' => 5], $f))->toBeTrue()
        ->and($engine->check(['feature' => 'n', 'op' => '<', 'value' => 5], $f))->toBeFalse()
        ->and($engine->check(['feature' => 'n', 'op' => '<=', 'value' => 5], $f))->toBeTrue()
        ->and($engine->check(['feature' => 'n', 'op' => '==', 'value' => 5], $f))->toBeTrue()
        ->and($engine->check(['feature' => 'n', 'op' => '!=', 'value' => 5], $f))->toBeFalse()
        ->and($engine->check(['feature' => 'h', 'op' => 'in', 'value' => [1, 3]], $f))->toBeTrue()
        ->and($engine->check(['feature' => 'h', 'op' => 'in', 'value' => [1, 2]], $f))->toBeFalse()
        ->and($engine->check(['feature' => 'missing', 'op' => '==', 'value' => 0], $f))->toBeFalse()
        ->and($engine->check(['feature' => 'n', 'op' => '??', 'value' => 1], $f))->toBeFalse();
});

it('does not match a null feature and caps the score at 100', function () {
    $engine = new RulesEngine;
    $rules = collect([
        dslRule(['all' => [['feature' => 'minutes_since_phone_change', 'op' => '<=', 'value' => 60]]], 50, 'A'),
        dslRule(['all' => [['feature' => 'x', 'op' => '==', 'value' => 1]]], 70, 'B'),
        dslRule(['all' => [['feature' => 'y', 'op' => '==', 'value' => 1]]], 70, 'C'),
    ]);

    $result = $engine->evaluate(EventType::Transfer, ['minutes_since_phone_change' => null, 'x' => 1, 'y' => 1], $rules);

    expect($result->score)->toBe(100)
        ->and($result->triggered)->toHaveCount(2);
});

it('writes human readable Arabic reasons', function () {
    $engine = new RulesEngine;
    $rule = dslRule(['all' => [['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 10]]], 45, 'تحويل كبير');

    $result = $engine->evaluate(EventType::Transfer, ['amount_to_avg_ratio' => 18.0], collect([$rule]));

    expect($result->triggered[0]['text'])->toContain('تحويل كبير')->toContain('نسبة المبلغ إلى متوسط الرصيد')->toContain('18')
        ->and($result->triggered[0]['source'])->toBe('rule')
        ->and($result->triggered[0]['weight'])->toBe(45);
});

it('reports blacklist and whitelist hits', function () {
    $result = (new RulesEngine)->evaluate(EventType::Login, ['blacklist_hit' => 1, 'whitelist_hit' => 0], collect());

    expect($result->blacklistHit)->toBeTrue()->and($result->whitelistHit)->toBeFalse();
});
