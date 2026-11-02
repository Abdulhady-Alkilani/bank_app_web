<?php

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\CaseStatus;
use App\Livewire\Analyst\AlertShow;
use App\Livewire\Analyst\CaseShow;
use App\Models\Approval;
use App\Models\InvestigationCase;
use App\Models\Setting;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Investigation\CaseService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

it('opens a case from an alert and links it', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();

    Livewire::actingAs($analyst)->test(AlertShow::class, ['alert' => $alert])->call('openCase');

    $case = InvestigationCase::first();
    expect($alert->fresh()->case_id)->toBe($case->id)
        ->and($case->assigned_to)->toBe($analyst->id)
        ->and($case->priority)->toBe($alert->priority);
});

it('merges related alerts into a case and adds notes', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert();
    $account = $alert->riskScore->transaction->account;
    $related = makeAnalystAlert(account: $account);
    $case = app(CaseService::class)->openFromAlert($alert, $analyst);

    Livewire::actingAs($analyst)->test(CaseShow::class, ['case' => $case])
        ->assertSee('#'.$related->id)
        ->call('mergeAlert', $related->id)
        ->set('note', 'Customer unreachable')
        ->call('addNote');

    expect($related->fresh()->case_id)->toBe($case->id)
        ->and($case->alerts()->count())->toBe(2)
        ->and($case->notes()->first()->note)->toBe('Customer unreachable')
        ->and($case->notes()->first()->is_ai_generated)->toBeFalse();
});

it('stores validated attachments on the private disk and serves them to authorized users only', function () {
    Storage::fake('local');
    $analyst = User::factory()->analyst()->create();
    $case = app(CaseService::class)->openFromAlert(makeAnalystAlert(), $analyst);

    Livewire::actingAs($analyst)->test(CaseShow::class, ['case' => $case])
        ->set('attachment', UploadedFile::fake()->create('evidence.exe', 10, 'application/x-msdownload'))
        ->call('uploadAttachment')
        ->assertHasErrors('attachment');

    Livewire::actingAs($analyst)->test(CaseShow::class, ['case' => $case])
        ->set('attachment', UploadedFile::fake()->create('too-big.pdf', 6000, 'application/pdf'))
        ->call('uploadAttachment')
        ->assertHasErrors('attachment');

    Livewire::actingAs($analyst)->test(CaseShow::class, ['case' => $case])
        ->set('attachment', UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'))
        ->call('uploadAttachment')
        ->assertHasNoErrors();

    $attachment = $case->attachments()->first();
    expect($attachment->file_name)->toBe('evidence.pdf');
    Storage::disk('local')->assertExists($attachment->file_path);

    $this->actingAs($analyst)->get(route('analyst.cases.attachments.download', $attachment))->assertOk();

    $case->update(['assigned_to' => User::factory()->analyst()->create()->id]);
    $this->actingAs($analyst)->get(route('analyst.cases.attachments.download', $attachment))->assertForbidden();
});

it('closes a low-value case immediately', function () {
    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert(transaction: ['amount' => 100]);
    $case = app(CaseService::class)->openFromAlert($alert, $analyst);

    Livewire::actingAs($analyst)->test(CaseShow::class, ['case' => $case])
        ->call('closeCase')
        ->assertHasErrors('closeReason')
        ->set('closeReason', 'Resolved')
        ->call('closeCase');

    expect($case->fresh()->status)->toBe(CaseStatus::Closed)->and(Approval::count())->toBe(0);
});

it('requires supervisor approval to close a high-value case', function () {
    Setting::updateOrCreate(['key' => 'cases.high_value_threshold'], ['value' => 50000]);
    app(SettingsService::class)->flush();

    $analyst = User::factory()->analyst()->create();
    $alert = makeAnalystAlert(transaction: ['amount' => 60000]);
    $case = app(CaseService::class)->openFromAlert($alert, $analyst);

    Livewire::actingAs($analyst)->test(CaseShow::class, ['case' => $case])
        ->set('closeReason', 'Resolved')
        ->call('closeCase');

    $approval = Approval::first();
    expect($case->fresh()->status)->toBe(CaseStatus::PendingApproval)
        ->and($approval->type)->toBe(ApprovalType::CloseHighValueCase)
        ->and($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->requested_by)->toBe($analyst->id);

    app(ApprovalService::class)->approve($approval, User::factory()->supervisor()->create());
    expect($case->fresh()->status)->toBe(CaseStatus::Closed);
});
