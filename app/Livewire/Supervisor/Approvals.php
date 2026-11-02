<?php

namespace App\Livewire\Supervisor;

use App\Enums\ApprovalStatus;
use App\Exceptions\ApprovalException;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Approval;
use App\Services\Approvals\ApprovalService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Approvals extends Component
{
    use AuthorizesPermission;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public ?string $flash = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorizePermission('approvals.approve');
    }

    public function approve(int $id, ApprovalService $approvals): void
    {
        $this->authorizePermission('approvals.approve');
        $this->flash = $this->errorMessage = null;

        try {
            $approvals->approve(Approval::findOrFail($id), auth()->user());
            $this->flash = __('supervisor.approvals.approved');
        } catch (ApprovalException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
        $this->resetValidation();
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function reject(ApprovalService $approvals): void
    {
        $this->authorizePermission('approvals.approve');
        $this->flash = $this->errorMessage = null;

        $this->validate(['rejectReason' => ['required', 'string', 'min:3', 'max:1000']]);

        try {
            $approvals->reject(Approval::findOrFail($this->rejectingId), auth()->user(), $this->rejectReason);
            $this->flash = __('supervisor.approvals.rejected');
            $this->cancelReject();
        } catch (ApprovalException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Short human description of the approval subject (account / case / beneficiary ...).
     */
    public function describe(Approval $approval): string
    {
        $subject = $approval->subject;
        if (! $subject) {
            return '—';
        }

        $name = class_basename($subject);
        $label = $subject->account_number ?? $subject->title ?? $subject->name ?? $subject->account_name ?? null;

        return $name.' #'.$subject->getKey().($label ? ' — '.$label : '');
    }

    public function render(): View
    {
        return view('livewire.supervisor.approvals', [
            'pending' => Approval::query()->with(['requester', 'subject'])->where('status', ApprovalStatus::Pending)->oldest()->get(),
            'recent' => Approval::query()->with(['requester', 'approver'])->where('status', '!=', ApprovalStatus::Pending)->latest('decided_at')->limit(10)->get(),
        ])->title(__('supervisor.approvals.title'));
    }
}
