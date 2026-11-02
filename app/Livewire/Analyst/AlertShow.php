<?php

namespace App\Livewire\Analyst;

use App\Enums\AccountActionType;
use App\Enums\AnalystDecision;
use App\Models\Alert;
use App\Models\InvestigationCase;
use App\Services\Investigation\AlertActionService;
use App\Services\Investigation\AlertDecisionService;
use App\Services\Investigation\AlertInsights;
use App\Services\Investigation\CaseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class AlertShow extends Component
{
    public Alert $alert;

    public string $reason = '';

    public string $actionReason = '';

    public function mount(Alert $alert): void
    {
        Gate::authorize('view', $alert);

        $this->alert = $alert;
    }

    public function decide(string $decision, AlertDecisionService $service): void
    {
        Gate::authorize('decide', $this->alert);

        $this->validate(
            ['reason' => ['required', 'string', 'min:3', 'max:2000']],
            attributes: ['reason' => __('analyst.alert.decision_reason')],
        );

        $service->decide($this->alert, AnalystDecision::from($decision), $this->reason, auth()->user());

        $this->reason = '';
        $this->alert->refresh();
        session()->now('success', __('analyst.alert.decision_saved'));
    }

    public function performAction(string $type, AlertActionService $service): void
    {
        Gate::authorize('act', $this->alert);

        $this->validate(
            ['actionReason' => ['required', 'string', 'min:3', 'max:2000']],
            attributes: ['actionReason' => __('analyst.alert.action_reason')],
        );

        $actionType = AccountActionType::from($type);

        try {
            $action = $service->perform($this->alert, $actionType, $this->actionReason, auth()->user());
        } catch (AuthorizationException) {
            $this->addError('actionReason', __('common.forbidden'));

            return;
        } catch (InvalidArgumentException $e) {
            $this->addError('actionReason', $e->getMessage());

            return;
        }

        $this->actionReason = '';
        session()->now('success', $action->status->value === 'pending_approval' ? __('analyst.alert.action_requested') : __('analyst.alert.action_done'));
    }

    public function openCase(CaseService $service): void
    {
        Gate::authorize('create', InvestigationCase::class);
        Gate::authorize('decide', $this->alert);

        $case = $service->openFromAlert($this->alert, auth()->user());

        $this->redirectRoute('analyst.cases.show', $case, navigate: true);
    }

    public function render(AlertInsights $insights, AlertActionService $actions): View
    {
        $this->alert->load(['riskScore.transaction.account.customer', 'riskScore.transaction.beneficiary', 'riskScore.transaction.device', 'riskScore.event.account.customer', 'riskScore.modelRun.model', 'riskScore.shadowModelRun.model', 'assignee', 'decider', 'case']);

        $account = $insights->account($this->alert);
        $user = auth()->user();

        return view('livewire.analyst.alert-show', [
            'account' => $account,
            'score' => $this->alert->riskScore,
            'reasons' => $insights->reasons($this->alert->riskScore),
            'features' => $insights->features($this->alert->riskScore),
            'timeline' => $insights->timeline($this->alert),
            'pending' => $actions->pendingFor($this->alert)->with(['requester'])->latest()->get(),
            'canDecide' => $user->can('decide', $this->alert),
            'canFreeze' => $user->hasPermission('accounts.freeze'),
            'canUnfreeze' => $user->hasPermission('accounts.unfreeze'),
            'decisions' => AnalystDecision::cases(),
        ])->title(__('analyst.alert.title', ['id' => $this->alert->id]));
    }
}
