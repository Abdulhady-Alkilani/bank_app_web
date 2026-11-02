<?php

namespace App\Livewire\Analyst;

use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\Priority;
use App\Models\Alert;
use App\Models\InvestigationCase;
use App\Services\Investigation\AlertQueue;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(AlertQueue $queue): View
    {
        $user = auth()->user();
        $openStatuses = [AlertStatus::Open, AlertStatus::InReview, AlertStatus::Escalated, AlertStatus::PendingInfo];

        $visible = fn () => $queue->query($user);

        return view('livewire.analyst.dashboard', [
            'myOpenAlerts' => Alert::query()->where('assigned_to', $user->id)->whereIn('status', $openStatuses)->count(),
            'unassigned' => Alert::query()->whereNull('assigned_to')->whereIn('status', $openStatuses)->count(),
            'criticalAlerts' => $visible()->whereIn('alerts.status', $openStatuses)->where('alerts.priority', Priority::Critical)->reorder()->count(),
            'myCases' => InvestigationCase::query()->where('assigned_to', $user->id)->where('status', '!=', CaseStatus::Closed)->count(),
            'recent' => $visible()->whereIn('alerts.status', $openStatuses)->where('risk_scores.score', '>=', 75)->reorder()->orderByDesc('alerts.created_at')->limit(8)->get(),
        ])->title(__('analyst.dashboard.title'));
    }
}
