<?php

namespace App\Livewire\Analyst;

use App\Enums\CaseStatus;
use App\Models\Alert;
use App\Models\CaseAttachment;
use App\Models\InvestigationCase;
use App\Services\Investigation\AlertInsights;
use App\Services\Investigation\AlertQueue;
use App\Services\Investigation\CaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CaseShow extends Component
{
    use WithFileUploads;

    public InvestigationCase $case;

    public string $note = '';

    public string $closeReason = '';

    public string $newStatus = '';

    public ?int $mergeAlertId = null;

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    public function mount(InvestigationCase $case): void
    {
        Gate::authorize('view', $case);

        $this->case = $case;
        $this->newStatus = $case->status->value;
    }

    public function addNote(CaseService $service): void
    {
        Gate::authorize('update', $this->case);
        $this->validate(['note' => ['required', 'string', 'min:2', 'max:5000']], attributes: ['note' => __('analyst.cases.note')]);

        $service->addNote($this->case, auth()->user(), $this->note);
        $this->reset('note');
    }

    public function uploadAttachment(CaseService $service): void
    {
        Gate::authorize('update', $this->case);
        $this->validate([
            'attachment' => ['required', 'file', 'max:5120', 'mimes:pdf,png,jpg,jpeg,txt,csv,doc,docx,xls,xlsx'],
        ], attributes: ['attachment' => __('analyst.cases.attachment')]);

        $service->attach($this->case, $this->attachment, auth()->user());
        $this->reset('attachment');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        Gate::authorize('update', $this->case);

        $attachment = CaseAttachment::query()->where('case_id', $this->case->id)->findOrFail($attachmentId);
        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();
    }

    public function claim(CaseService $service): void
    {
        Gate::authorize('update', $this->case);
        $service->claim($this->case, auth()->user());
    }

    public function changeStatus(CaseService $service): void
    {
        Gate::authorize('update', $this->case);

        $status = CaseStatus::tryFrom($this->newStatus);
        if (! $status || in_array($status, [CaseStatus::Closed, CaseStatus::PendingApproval], true)) {
            $this->addError('newStatus', __('analyst.errors.invalid_status'));

            return;
        }
        if (in_array($this->case->status, [CaseStatus::Closed, CaseStatus::PendingApproval], true)) {
            $this->addError('newStatus', __('analyst.errors.case_not_closable'));

            return;
        }

        $service->changeStatus($this->case, $status, auth()->user());
    }

    public function mergeAlert(int $alertId, CaseService $service): void
    {
        Gate::authorize('update', $this->case);
        Gate::authorize('view', Alert::query()->findOrFail($alertId));

        $service->mergeAlerts($this->case, [$alertId], auth()->user());
        $this->mergeAlertId = null;
    }

    public function mergeById(CaseService $service): void
    {
        $this->validate(['mergeAlertId' => ['required', 'integer', 'exists:alerts,id']], attributes: ['mergeAlertId' => __('analyst.cases.alert_id')]);

        $this->mergeAlert((int) $this->mergeAlertId, $service);
    }

    public function closeCase(CaseService $service): void
    {
        Gate::authorize('close', $this->case);
        $this->validate(['closeReason' => ['required', 'string', 'min:3', 'max:2000']], attributes: ['closeReason' => __('analyst.cases.close_reason')]);

        try {
            $approval = $service->close($this->case, auth()->user(), $this->closeReason);
        } catch (InvalidArgumentException $e) {
            $this->addError('closeReason', $e->getMessage());

            return;
        }

        $this->reset('closeReason');
        session()->now('success', $approval ? __('analyst.cases.close_requested') : __('analyst.cases.closed'));
    }

    public function render(AlertInsights $insights, AlertQueue $queue, CaseService $service): View
    {
        $this->case->refresh()->load(['assignee', 'notes.user', 'attachments.uploader', 'alerts.riskScore.transaction.account', 'alerts.riskScore.event.account', 'aiProposals']);

        $accountIds = $this->case->alerts->map(fn (Alert $a) => $insights->account($a)?->id)->filter()->unique()->values();

        $related = $accountIds->isEmpty() ? collect() : $queue->query(auth()->user())
            ->where(fn ($q) => $q->whereIn('transactions.account_id', $accountIds)->orWhereHas('riskScore.event', fn ($e) => $e->whereIn('account_id', $accountIds)))
            ->where(fn ($q) => $q->whereNull('alerts.case_id')->orWhere('alerts.case_id', '!=', $this->case->id))
            ->limit(8)
            ->get();

        $closed = in_array($this->case->status, [CaseStatus::Closed, CaseStatus::PendingApproval], true);

        return view('livewire.analyst.case-show', [
            'insights' => $insights,
            'related' => $related,
            'total' => $service->totalAmount($this->case),
            'closed' => $closed,
            'statuses' => collect(CaseStatus::cases())->reject(fn ($s) => in_array($s, [CaseStatus::Closed, CaseStatus::PendingApproval], true))->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ])->title(__('analyst.cases.case_heading', ['id' => $this->case->id]));
    }
}
