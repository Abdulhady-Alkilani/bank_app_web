<?php

namespace App\Services\Investigation;

use App\Enums\AlertStatus;
use App\Enums\ApprovalType;
use App\Enums\CaseStatus;
use App\Models\Alert;
use App\Models\Approval;
use App\Models\CaseAttachment;
use App\Models\CaseNote;
use App\Models\InvestigationCase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CaseService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ApprovalService $approvals,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Open a case from an alert (or return the alert's existing case).
     */
    public function openFromAlert(Alert $alert, User $user, ?string $title = null): InvestigationCase
    {
        if ($alert->case_id && $alert->case) {
            return $alert->case;
        }

        return DB::transaction(function () use ($alert, $user, $title) {
            $case = InvestigationCase::create([
                'title' => $title ?: __('analyst.cases.default_title', ['id' => $alert->id]),
                'assigned_to' => $user->getKey(),
                'status' => CaseStatus::Open,
                'priority' => $alert->priority,
            ]);

            $alert->update([
                'case_id' => $case->id,
                'assigned_to' => $alert->assigned_to ?? $user->getKey(),
                'status' => $alert->status === AlertStatus::Open ? AlertStatus::InReview : $alert->status,
            ]);

            $this->audit->log('case.opened', $case, [], ['alert_id' => $alert->id], $user);

            return $case;
        });
    }

    /**
     * Attach related alerts to a case (alerts already in another case are moved).
     *
     * @param  array<int, int>  $alertIds
     */
    public function mergeAlerts(InvestigationCase $case, array $alertIds, User $user): int
    {
        $alerts = Alert::query()->whereIn('id', $alertIds)->where(fn ($q) => $q->whereNull('case_id')->orWhere('case_id', '!=', $case->id))->get();

        foreach ($alerts as $alert) {
            $previous = $alert->case_id;
            $alert->update(['case_id' => $case->id, 'assigned_to' => $alert->assigned_to ?? $user->getKey()]);
            $this->audit->log('case.alert_merged', $case, ['previous_case_id' => $previous], ['alert_id' => $alert->id], $user);
        }

        $this->escalatePriority($case);

        return $alerts->count();
    }

    public function claim(InvestigationCase $case, User $user): InvestigationCase
    {
        if ($case->assigned_to === null) {
            $case->update(['assigned_to' => $user->getKey()]);
            $this->audit->log('case.claimed', $case, [], ['assigned_to' => $user->getKey()], $user);
        }

        return $case;
    }

    public function addNote(InvestigationCase $case, User $user, string $note, bool $isAi = false): CaseNote
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException(__('analyst.errors.note_required'));
        }

        $created = $case->notes()->create(['user_id' => $user->getKey(), 'note' => $note, 'is_ai_generated' => $isAi]);
        $this->audit->log('case.note_added', $case, [], ['note_id' => $created->id, 'ai' => $isAi], $user);

        return $created;
    }

    /**
     * Store an uploaded file on the private local disk (storage/app/private/cases/{id}).
     */
    public function attach(InvestigationCase $case, UploadedFile $file, User $user): CaseAttachment
    {
        $path = $file->store('cases/'.$case->id, 'local');

        $attachment = $case->attachments()->create([
            'uploaded_by' => $user->getKey(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $this->audit->log('case.attachment_added', $case, [], ['attachment_id' => $attachment->id, 'file' => $attachment->file_name], $user);

        return $attachment;
    }

    public function changeStatus(InvestigationCase $case, CaseStatus $status, User $user): InvestigationCase
    {
        if ($status === CaseStatus::Closed) {
            throw new InvalidArgumentException('Use close() to close a case.');
        }
        if ($status === CaseStatus::PendingApproval) {
            throw new InvalidArgumentException('Pending approval is set by the close workflow.');
        }

        $old = $case->status->value;
        $case->update(['status' => $status]);
        $this->audit->log('case.status_changed', $case, ['status' => $old], ['status' => $status->value], $user);

        return $case;
    }

    /**
     * Sum of transaction amounts across the case's alerts.
     */
    public function totalAmount(InvestigationCase $case): float
    {
        $transactionIds = $case->alerts()->with('riskScore')->get()->pluck('riskScore.transaction_id')->filter()->unique();

        return (float) Transaction::query()->whereIn('id', $transactionIds)->sum('amount');
    }

    /**
     * Close a case. High-value cases (total >= cases.high_value_threshold) require four-eyes approval instead:
     * the case moves to pending_approval and the created Approval is returned; otherwise null is returned and the case is closed.
     */
    public function close(InvestigationCase $case, User $user, string $reason): ?Approval
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('analyst.errors.reason_required'));
        }
        if ($case->status === CaseStatus::Closed || $case->status === CaseStatus::PendingApproval) {
            throw new InvalidArgumentException(__('analyst.errors.case_not_closable'));
        }

        $threshold = (float) $this->settings->get('cases.high_value_threshold', 50000);

        return DB::transaction(function () use ($case, $user, $reason, $threshold) {
            $this->addNote($case, $user, __('analyst.cases.closing_note', ['reason' => $reason]));

            if ($this->totalAmount($case) >= $threshold) {
                $approval = $this->approvals->request(ApprovalType::CloseHighValueCase, $case, $user, $reason);
                $case->update(['status' => CaseStatus::PendingApproval]);
                $this->audit->log('case.close_requested', $case, [], ['approval_id' => $approval->id], $user);

                return $approval;
            }

            $case->update(['status' => CaseStatus::Closed]);
            $this->audit->log('case.closed', $case, [], ['reason' => $reason], $user);

            return null;
        });
    }

    private function escalatePriority(InvestigationCase $case): void
    {
        $order = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $highest = $case->alerts()->get()->sortByDesc(fn (Alert $a) => $order[$a->priority->value])->first()?->priority;

        if ($highest && $order[$highest->value] > $order[$case->priority->value]) {
            $case->update(['priority' => $highest]);
        }
    }
}
