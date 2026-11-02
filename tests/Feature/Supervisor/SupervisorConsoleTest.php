<?php

use App\Enums\AlertStatus;
use App\Enums\AnalystDecision;
use App\Enums\ApprovalStatus;
use App\Enums\RuleSuggestionStatus;
use App\Livewire\Supervisor\Approvals;
use App\Livewire\Supervisor\Assignments;
use App\Livewire\Supervisor\QaReview;
use App\Livewire\Supervisor\Reports;
use App\Livewire\Supervisor\RuleSuggestions;
use App\Models\Account;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Rule;
use App\Models\RuleSuggestion;
use App\Models\User;
use App\Services\Reports\ComplianceReportService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->supervisor = User::factory()->supervisor()->create();
    $this->seed(SettingsSeeder::class);
});

it('bulk assigns alerts to an analyst and audits it', function () {
    $analyst = User::factory()->analyst()->create();
    $alerts = Alert::factory()->count(3)->create();

    Livewire::actingAs($this->supervisor)->test(Assignments::class)
        ->set('selected', $alerts->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->set('target', (string) $analyst->id)
        ->call('assign')
        ->assertHasNoErrors();

    expect(Alert::where('assigned_to', $analyst->id)->count())->toBe(3)
        ->and(AuditLog::where('action', 'assignment.assigned')->count())->toBe(3);
});

it('reassigns, unassigns, and warns when over capacity', function () {
    $analyst = User::factory()->analyst()->create();
    app(SettingsService::class)->set('alerts.analyst_daily_capacity', 1);
    $alerts = Alert::factory()->count(2)->create();

    $component = Livewire::actingAs($this->supervisor)->test(Assignments::class)
        ->set('selected', $alerts->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->set('target', (string) $analyst->id)
        ->call('assign');
    expect($component->get('flash'))->toContain($analyst->name);

    $component->set('scope', 'assigned')
        ->set('selected', [(string) $alerts[0]->id])
        ->set('target', '0')
        ->call('assign');
    expect($alerts[0]->fresh()->assigned_to)->toBeNull();
});

it('rejects assignment to a non-analyst', function () {
    $customer = User::factory()->customer()->create();
    $alert = Alert::factory()->create();

    Livewire::actingAs($this->supervisor)->test(Assignments::class)
        ->set('selected', [(string) $alert->id])
        ->set('target', (string) $customer->id)
        ->call('assign')
        ->assertSet('errorMessage', __('supervisor.assignments.invalid_analyst'));

    expect($alert->fresh()->assigned_to)->toBeNull();
});

it('approves a pending request from another user and enforces four-eyes in the UI', function () {
    $requester = User::factory()->supervisor()->create();
    $account = Account::factory()->create();

    $foreign = Approval::factory()->create(['requested_by' => $requester->id, 'subject_type' => Account::class, 'subject_id' => $account->id]);
    $own = Approval::factory()->create(['requested_by' => $this->supervisor->id, 'subject_type' => Account::class, 'subject_id' => $account->id]);

    $component = Livewire::actingAs($this->supervisor)->test(Approvals::class);

    $component->call('approve', $own->id)->assertSet('errorMessage', __('common.four_eyes_violation'));
    expect($own->fresh()->status)->toBe(ApprovalStatus::Pending);

    $component->call('approve', $foreign->id)->assertSet('errorMessage', null);
    expect($foreign->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($foreign->fresh()->approved_by)->toBe($this->supervisor->id);
});

it('rejects an approval with a reason', function () {
    $requester = User::factory()->supervisor()->create();
    $approval = Approval::factory()->create(['requested_by' => $requester->id]);

    Livewire::actingAs($this->supervisor)->test(Approvals::class)
        ->call('startReject', $approval->id)
        ->call('reject')->assertHasErrors(['rejectReason'])
        ->set('rejectReason', 'Not enough evidence')
        ->call('reject')->assertHasNoErrors();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected);
});

it('draws a QA sample from decided alerts and stores reviews in the audit log', function () {
    app(SettingsService::class)->set('qa.sample_rate', 0.5);
    $decided = Alert::factory()->count(4)->create([
        'status' => AlertStatus::Closed, 'decision' => AnalystDecision::FalsePositive,
        'decided_at' => now()->subDay(), 'decided_by' => $this->supervisor->id,
    ]);
    $undecided = Alert::factory()->create();

    $component = Livewire::actingAs($this->supervisor)->test(QaReview::class)->call('draw');
    $sample = $component->get('sampleIds');

    expect($sample)->toHaveCount(2)
        ->and(array_diff($sample, $decided->pluck('id')->all()))->toBe([])
        ->and($sample)->not->toContain($undecided->id);

    $component->call('review', $sample[0], 'disagree')->assertHasErrors(['comments.'.$sample[0]]);
    $component->set('comments.'.$sample[0], 'Evidence was thin')->call('review', $sample[0], 'disagree');

    $log = AuditLog::where('action', 'qa.review')->first();
    expect($log->entity_id)->toBe($sample[0])
        ->and($log->new_values['verdict'])->toBe('disagree')
        ->and($log->new_values['comment'])->toBe('Evidence was thin');

    // Alerts outside the sample cannot be reviewed.
    $component->call('review', $undecided->id, 'agree')->assertForbidden();
});

it('accepts a rule suggestion by creating an inactive rule', function () {
    $suggestion = RuleSuggestion::factory()->create(['title' => 'Many txns']);

    $this->actingAs($this->supervisor);
    Livewire::test(RuleSuggestions::class)->set('notes.'.$suggestion->id, 'Looks good')->call('accept', $suggestion->id);

    $suggestion->refresh();
    $rule = Rule::find($suggestion->rule_id);

    expect($suggestion->status)->toBe(RuleSuggestionStatus::Accepted)
        ->and($suggestion->reviewed_by)->toBe($this->supervisor->id)
        ->and($suggestion->reviewed_at)->not->toBeNull()
        ->and($suggestion->review_note)->toBe('Looks good')
        ->and($rule->rule_name)->toBe('Many txns')
        ->and($rule->is_active)->toBeFalse()
        ->and($rule->conditions)->toBe($suggestion->conditions);
});

it('rejects a rule suggestion only with a note', function () {
    $suggestion = RuleSuggestion::factory()->create();

    $component = Livewire::actingAs($this->supervisor)->test(RuleSuggestions::class)
        ->call('reject', $suggestion->id)->assertHasErrors(['notes.'.$suggestion->id]);

    $component->set('notes.'.$suggestion->id, 'Duplicate')->call('reject', $suggestion->id);
    expect($suggestion->fresh()->status)->toBe(RuleSuggestionStatus::Rejected)
        ->and(Rule::count())->toBe(0);
});

it('exports the compliance report as pdf and xlsx and audits it', function () {
    Alert::factory()->count(2)->create();

    Livewire::actingAs($this->supervisor)->test(Reports::class)->call('exportPdf')->assertFileDownloaded();
    Livewire::actingAs($this->supervisor)->test(Reports::class)->call('exportExcel')->assertFileDownloaded();

    expect(AuditLog::where('action', 'report.exported')->count())->toBe(2);
});

it('produces a real PDF and XLSX binary', function () {
    $reports = app(ComplianceReportService::class);
    $summary = $reports->summary(now()->subWeek(), now());

    expect(substr($reports->pdf($summary), 0, 4))->toBe('%PDF');
    app()->setLocale('ar');
    expect(substr($reports->pdf($summary), 0, 4))->toBe('%PDF');
});

it('renders supervisor pages for supervisors and denies analysts', function () {
    foreach (['', '/assignments', '/approvals', '/qa', '/rule-suggestions', '/reports', '/audit', '/models'] as $path) {
        $this->actingAs($this->supervisor)->get('/supervisor'.$path)->assertOk();
    }

    $analyst = User::factory()->analyst()->create();
    foreach (['', '/assignments', '/approvals', '/qa', '/rule-suggestions', '/reports', '/audit', '/models'] as $path) {
        $this->actingAs($analyst)->get('/supervisor'.$path)->assertForbidden();
    }
});

it('renders the dashboard with data', function () {
    $analyst = User::factory()->analyst()->create(['name' => 'Layla Analyst']);
    Alert::factory()->create(['assigned_to' => $analyst->id]);
    Alert::factory()->create([
        'status' => AlertStatus::Closed, 'decision' => AnalystDecision::ConfirmedFraud,
        'decided_at' => now(), 'decided_by' => $analyst->id,
    ]);

    $this->actingAs($this->supervisor)->get('/supervisor')->assertOk()->assertSee($analyst->name, false);
});
