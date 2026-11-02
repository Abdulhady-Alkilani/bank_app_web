<?php

use App\Enums\Decision;
use App\Enums\LabelSource;
use App\Enums\Priority;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Labels\LabelService;
use App\Services\Settings\SettingsService;
use App\Support\Masker;

it('masks sensitive values', function () {
    expect(Masker::maskAccount('1012345678'))->toBe('10******78')
        ->and(Masker::maskPhone('+967711000001'))->toBe('+967******001')
        ->and(Masker::maskEmail('ahmed@bank.test'))->toBe('a****@bank.test')
        ->and(Masker::maskIp('192.168.10.5'))->toBe('192.168.*.*')
        ->and(Masker::maskName('Ahmed Ali'))->toBe('A*** A***')
        ->and(Masker::maskName('أحمد علي'))->toBe('أ*** ع***')
        ->and(Masker::maskAccount(null))->toBeNull();
});

it('masks selected keys recursively', function () {
    $masked = Masker::maskArray([
        'id' => 5,
        'account_number' => '1012345678',
        'customer' => ['name' => 'Ahmed Ali', 'phone' => '+967711000001', 'city' => 'Sanaa'],
    ], ['account_number', 'name', 'phone']);

    expect($masked['id'])->toBe(5)
        ->and($masked['account_number'])->toBe('10******78')
        ->and($masked['customer']['name'])->toBe('A*** A***')
        ->and($masked['customer']['phone'])->toBe('+967******001')
        ->and($masked['customer']['city'])->toBe('Sanaa');
});

it('stores and caches settings', function () {
    $settings = app(SettingsService::class);

    expect($settings->get('missing', 'fallback'))->toBe('fallback');

    $settings->set('decision.allow_max', 39, 'desc');
    expect($settings->get('decision.allow_max'))->toBe(39)
        ->and($settings->all())->toHaveKey('decision.allow_max');

    $settings->get('decision.allow_max'); // warm the cache
    $settings->set('decision.allow_max', 45);
    expect($settings->get('decision.allow_max'))->toBe(45);

    $settings->set('flag', false);
    expect($settings->get('flag', true))->toBeFalse();

    $settings->set('list', ['a' => 1]);
    expect($settings->get('list'))->toBe(['a' => 1]);
});

it('writes audit logs with the acting user', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $log = app(AuditLogger::class)->log('thing.updated', $user, ['a' => 1], ['a' => 2]);

    expect($log->user_id)->toBe($user->id)
        ->and($log->entity_type)->toBe(User::class)
        ->and($log->entity_id)->toBe($user->id)
        ->and($log->old_values)->toBe(['a' => 1])
        ->and($log->new_values)->toBe(['a' => 2]);

    expect(app(AuditLogger::class)->log('x', null, [], [], $user)->entity_type)->toBeNull();
});

it('records labels', function () {
    $transaction = Transaction::factory()->create();
    $analyst = User::factory()->analyst()->create();

    $label = app(LabelService::class)->record($transaction, null, null, true, LabelSource::Analyst, $analyst);

    expect($label->is_fraud)->toBeTrue()
        ->and($label->source)->toBe(LabelSource::Analyst)
        ->and($label->labeled_by)->toBe($analyst->id)
        ->and($label->transaction_id)->toBe($transaction->id);
});

it('translates enum labels and exposes colors', function () {
    expect(Priority::Critical->label())->toBe('حرجة')
        ->and(Decision::Block->color())->toContain('red')
        ->and(Priority::values())->toBe(['low', 'medium', 'high', 'critical']);

    app()->setLocale('en');
    expect(Priority::Critical->label())->toBe('Critical');
});

it('renders the badge component for enums', function () {
    $html = $this->blade('<x-ui.badge :value="$p" />', ['p' => Priority::High]);

    $html->assertSee('عالية')->assertSee('orange');
});
