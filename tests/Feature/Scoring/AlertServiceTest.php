<?php

use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\Decision;
use App\Enums\Priority;
use App\Models\Account;
use App\Models\Alert;
use App\Models\InvestigationCase;
use App\Models\RiskScore;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\HighRiskAlertNotification;
use App\Services\Alerts\AlertService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->alerts = app(AlertService::class);
});

function riskFor(int $score, ?Account $account = null): RiskScore
{
    $account ??= Account::factory()->create();
    $tx = Transaction::factory()->create(['account_id' => $account->id]);
    $decision = $score >= 75 ? Decision::Block : ($score >= 40 ? Decision::Challenge : Decision::Allow);

    return RiskScore::factory()->create(['transaction_id' => $tx->id, 'score' => $score, 'decision' => $decision]);
}

it('maps scores to priorities', function (int $score, Priority $priority) {
    expect($this->alerts->priorityFor($score))->toBe($priority);
})->with([
    [95, Priority::Critical], [90, Priority::Critical], [89, Priority::High], [75, Priority::High],
    [74, Priority::Medium], [55, Priority::Medium], [54, Priority::Low], [40, Priority::Low],
]);

it('does not alert on allow decisions and is idempotent per risk score', function () {
    expect($this->alerts->createForRiskScore(riskFor(10)))->toBeNull();

    $risk = riskFor(60);
    $first = $this->alerts->createForRiskScore($risk);
    $second = $this->alerts->createForRiskScore($risk);

    expect($second->id)->toBe($first->id)->and(Alert::count())->toBe(1)
        ->and($first->priority)->toBe(Priority::Medium)
        ->and($first->status)->toBe(AlertStatus::Open);
});

it('assigns to the active analyst with the fewest open alerts', function () {
    $busy = User::factory()->analyst()->create();
    $free = User::factory()->analyst()->create();
    $inactive = User::factory()->analyst()->inactive()->create();
    Alert::factory()->count(2)->create(['assigned_to' => $busy->id, 'status' => AlertStatus::Open]);
    Alert::factory()->count(5)->create(['assigned_to' => $free->id, 'status' => AlertStatus::Closed]);

    $alert = $this->alerts->createForRiskScore(riskFor(60));

    expect($alert->assigned_to)->toBe($free->id)->and($alert->assigned_to)->not->toBe($inactive->id);

    $next = $this->alerts->createForRiskScore(riskFor(60));
    $third = $this->alerts->createForRiskScore(riskFor(60));
    expect([$next->assigned_to, $third->assigned_to])->toContain($busy->id);
});

it('leaves alerts unassigned when no analyst has capacity', function () {
    app(SettingsService::class)->set('alerts.analyst_daily_capacity', 1);
    $analyst = User::factory()->analyst()->create();

    $one = $this->alerts->createForRiskScore(riskFor(60));
    $two = $this->alerts->createForRiskScore(riskFor(60));

    expect($one->assigned_to)->toBe($analyst->id)->and($two->assigned_to)->toBeNull();
});

it('creates a case for high alerts and merges later alerts of the same account into the open case', function () {
    User::factory()->analyst()->create();
    $account = Account::factory()->create();

    $medium = $this->alerts->createForRiskScore(riskFor(60, $account));
    expect($medium->case_id)->toBeNull();

    $high = $this->alerts->createForRiskScore(riskFor(80, $account));
    expect($high->case_id)->not->toBeNull()->and(InvestigationCase::count())->toBe(1);

    $critical = $this->alerts->createForRiskScore(riskFor(95, $account));
    expect($critical->case_id)->toBe($high->case_id)
        ->and(InvestigationCase::first()->priority)->toBe(Priority::Critical);

    // A closed case is never reused.
    InvestigationCase::first()->update(['status' => CaseStatus::Closed]);
    $after = $this->alerts->createForRiskScore(riskFor(85, $account));
    expect($after->case_id)->not->toBe($high->case_id)->and(InvestigationCase::count())->toBe(2);
});

it('notifies the assignee, and every analyst for critical alerts', function () {
    Notification::fake();
    $a = User::factory()->analyst()->create();
    $b = User::factory()->analyst()->create();
    User::factory()->supervisor()->create();

    $this->alerts->createForRiskScore(riskFor(60));
    Notification::assertSentToTimes($a, HighRiskAlertNotification::class, 1);
    Notification::assertNotSentTo($b, HighRiskAlertNotification::class);

    $this->alerts->createForRiskScore(riskFor(95));
    Notification::assertSentTo($b, HighRiskAlertNotification::class, function ($n) use ($b) {
        $data = $n->toArray($b);

        return $data['score'] === 95 && isset($data['alert_id'], $data['account'], $data['message']) && str_contains($data['account'], '*');
    });
});

it('stores the notification in the database channel', function () {
    $analyst = User::factory()->analyst()->create();

    $this->alerts->createForRiskScore(riskFor(95));

    expect($analyst->notifications()->count())->toBe(1)
        ->and($analyst->notifications()->first()->data)->toHaveKeys(['alert_id', 'score', 'account', 'message']);
});
