<?php

use App\Enums\Priority;
use App\Livewire\Analyst\AlertQueue;
use App\Models\User;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

it('shows analysts their assigned and unassigned alerts only', function () {
    $analyst = User::factory()->analyst()->create();
    $other = User::factory()->analyst()->create();

    $mine = makeAnalystAlert(['assigned_to' => $analyst->id]);
    $free = makeAnalystAlert();
    $theirs = makeAnalystAlert(['assigned_to' => $other->id]);

    Livewire::actingAs($analyst)->test(AlertQueue::class)
        ->assertSee('#', false)
        ->assertViewHas('alerts', fn ($alerts) => $alerts->pluck('id')->sort()->values()->all() === collect([$mine->id, $free->id])->sort()->values()->all());

    Livewire::actingAs(User::factory()->supervisor()->create())->test(AlertQueue::class)
        ->assertViewHas('alerts', fn ($alerts) => $alerts->total() === 3);

    expect($theirs->id)->not->toBeNull();
});

it('sorts by priority then score descending', function () {
    $analyst = User::factory()->analyst()->create();
    $lowScoreCritical = makeAnalystAlert(['priority' => Priority::Critical], 55);
    $highScoreHigh = makeAnalystAlert(['priority' => Priority::High], 95);
    $midHigh = makeAnalystAlert(['priority' => Priority::High], 70);

    Livewire::actingAs($analyst)->test(AlertQueue::class)
        ->assertViewHas('alerts', fn ($a) => $a->pluck('id')->all() === [$lowScoreCritical->id, $highScoreHigh->id, $midHigh->id]);
});

it('filters by status, priority, score, channel, amount, date and assignment', function () {
    $analyst = User::factory()->analyst()->create();
    $a = makeAnalystAlert(['priority' => Priority::Critical, 'assigned_to' => $analyst->id], 90, ['channel' => 'web', 'amount' => 9000]);
    $b = makeAnalystAlert(['priority' => Priority::Low], 30, ['channel' => 'mobile', 'amount' => 100]);

    $ids = fn ($component) => $component->viewData('alerts')->pluck('id')->all();

    $c = Livewire::actingAs($analyst)->test(AlertQueue::class);
    expect($ids($c))->toHaveCount(2);

    expect($ids($c->set('priority', 'critical')))->toBe([$a->id]);
    expect($ids($c->set('priority', '')->set('minScore', '50')))->toBe([$a->id]);
    expect($ids($c->set('minScore', '')->set('maxScore', '40')))->toBe([$b->id]);
    expect($ids($c->set('maxScore', '')->set('channel', 'mobile')))->toBe([$b->id]);
    expect($ids($c->set('channel', '')->set('minAmount', '5000')))->toBe([$a->id]);
    expect($ids($c->set('minAmount', '')->set('maxAmount', '500')))->toBe([$b->id]);
    expect($ids($c->set('maxAmount', '')->set('mine', true)))->toBe([$a->id]);
    expect($ids($c->set('mine', false)->set('status', 'closed')))->toBe([]);
    expect($ids($c->set('status', '')->set('dateFrom', now()->addDay()->toDateString())))->toBe([]);
    expect($ids($c->set('dateFrom', now()->toDateString())->set('dateTo', now()->toDateString())))->toHaveCount(2);
});

it('forbids customers from the queue', function () {
    $this->actingAs(User::factory()->customer()->create())->get('/analyst/alerts')->assertForbidden();
});
