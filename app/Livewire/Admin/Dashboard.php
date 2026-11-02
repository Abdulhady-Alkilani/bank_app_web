<?php

namespace App\Livewire\Admin;

use App\Models\Alert;
use App\Models\ApiClient;
use App\Models\Rule;
use App\Models\User;
use App\Services\Admin\SystemHealthService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(SystemHealthService $health): View
    {
        return view('livewire.admin.dashboard', [
            'users' => User::count(),
            'activeRules' => Rule::where('is_active', true)->count(),
            'apiClients' => ApiClient::where('is_active', true)->whereNull('revoked_at')->count(),
            'aiStatus' => $health->aiStatus(),
            'backlog' => $health->queueBacklog(),
            'failed' => $health->failedJobsCount(),
            'alerts24h' => Alert::where('created_at', '>=', now()->subDay())->count(),
        ])->title(__('admin.dashboard.title'));
    }
}
