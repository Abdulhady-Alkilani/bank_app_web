<?php

use App\Livewire\Admin\Rules;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Watchlists;
use App\Models\AuditLog;
use App\Models\Rule;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\Settings\SettingsService;
use App\Support\RuleConditions;
use Database\Seeders\SettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seed(SettingsSeeder::class);
});

it('saves valid decision settings and audits old/new values', function () {
    Livewire::actingAs($this->admin)->test(Settings::class)
        ->set('form.allow_max', 30)
        ->set('form.challenge_max', 70)
        ->set('form.rule_weight', 0.5)
        ->set('form.model_weight', 0.5)
        ->set('form.capacity', 80)
        ->set('form.require_2fa', true)
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsService::class);
    expect($settings->get('decision.allow_max'))->toBe(30)
        ->and($settings->get('decision.challenge_max'))->toBe(70)
        ->and($settings->get('alerts.analyst_daily_capacity'))->toBe(80)
        ->and($settings->get('security.require_staff_2fa'))->toBeTrue();

    $log = AuditLog::where('action', 'settings.updated')->first();
    expect($log->old_values['decision.allow_max'])->toBe(39)
        ->and($log->new_values['decision.allow_max'])->toBe(30);
});

it('rejects thresholds where allow_max is not below challenge_max', function () {
    Livewire::actingAs($this->admin)->test(Settings::class)
        ->set('form.allow_max', 80)
        ->set('form.challenge_max', 70)
        ->call('save')
        ->assertHasErrors(['form.challenge_max']);

    Livewire::actingAs($this->admin)->test(Settings::class)
        ->set('form.challenge_max', 101)
        ->call('save')
        ->assertHasErrors(['form.challenge_max']);

    expect(app(SettingsService::class)->get('decision.allow_max'))->toBe(39);
});

it('rejects weights that do not sum to one', function () {
    Livewire::actingAs($this->admin)->test(Settings::class)
        ->set('form.rule_weight', 0.5)
        ->set('form.model_weight', 0.6)
        ->call('save')
        ->assertHasErrors(['form.model_weight']);

    expect(app(SettingsService::class)->get('scoring.rule_weight'))->toBe(0.4);
});

it('creates, edits, toggles and deletes rules with audit', function () {
    $component = Livewire::actingAs($this->admin)->test(Rules::class)
        ->call('create')
        ->set('ruleName', 'Big transfer')
        ->set('weight', 40)
        ->set('priority', 10)
        ->set('action', 'challenge')
        ->set('eventTypes', ['transfer'])
        ->set('logic', 'all')
        ->set('rows', [
            ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => '10'],
            ['feature' => 'beneficiary_is_new', 'op' => '==', 'value' => '1'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $rule = Rule::where('rule_name', 'Big transfer')->first();
    expect($rule->conditions)->toBe([
        'event_types' => ['transfer'],
        'all' => [
            ['feature' => 'amount_to_avg_ratio', 'op' => '>=', 'value' => 10],
            ['feature' => 'beneficiary_is_new', 'op' => '==', 'value' => 1],
        ],
    ])->and($rule->action)->toBe('challenge');

    $component->call('edit', $rule->id)->set('weight', 55)->call('save')->assertHasNoErrors();
    expect($rule->fresh()->weight)->toBe(55);

    $component->call('toggle', $rule->id);
    expect($rule->fresh()->is_active)->toBeFalse();

    $component->call('delete', $rule->id);
    expect(Rule::count())->toBe(0);

    expect(AuditLog::whereIn('action', ['rule.created', 'rule.updated', 'rule.toggled', 'rule.deleted'])->count())->toBe(4);
});

it('validates rule conditions', function () {
    Livewire::actingAs($this->admin)->test(Rules::class)
        ->call('create')
        ->set('ruleName', 'Bad')
        ->set('rows', [['feature' => 'not_a_feature', 'op' => '>=', 'value' => '1']])
        ->call('save')
        ->assertHasErrors(['conditions']);

    Livewire::actingAs($this->admin)->test(Rules::class)
        ->call('create')
        ->set('ruleName', 'No value')
        ->set('rows', [['feature' => 'amount', 'op' => '>=', 'value' => '']])
        ->call('save')
        ->assertHasErrors(['conditions']);

    expect(Rule::count())->toBe(0);
});

it('builds and validates condition arrays', function () {
    $conditions = RuleConditions::build('any', [['feature' => 'blacklist_hit', 'op' => '==', 'value' => '1'], ['feature' => 'hour_of_day', 'op' => 'in', 'value' => '1, 2,3']], ['login']);

    expect($conditions['any'][1]['value'])->toBe([1, 2, 3])
        ->and(RuleConditions::validate($conditions))->toBe([])
        ->and(RuleConditions::validate(['all' => []]))->not->toBe([]);
});

it('manages watchlist entries with audit', function () {
    $component = Livewire::actingAs($this->admin)->test(Watchlists::class)
        ->call('create')
        ->set('listType', 'blacklist')
        ->set('entityType', 'account')
        ->set('entityValue', 'ACC-123')
        ->set('reason', 'mule')
        ->call('save')
        ->assertHasNoErrors();

    $entry = Watchlist::first();
    expect($entry->entity_value)->toBe('ACC-123')->and($entry->added_at)->not->toBeNull();

    $component->call('edit', $entry->id)->set('listType', 'whitelist')->call('save');
    expect($entry->fresh()->list_type->value)->toBe('whitelist');

    $component->call('delete', $entry->id);
    expect(Watchlist::count())->toBe(0);
    expect(AuditLog::where('action', 'like', 'watchlist.%')->count())->toBe(3);

    Livewire::actingAs($this->admin)->test(Watchlists::class)
        ->call('create')->set('entityType', 'bogus')->set('entityValue', 'x')->call('save')
        ->assertHasErrors(['entityType']);
});
