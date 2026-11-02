<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Services\Admin\SystemHealthService;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class SystemHealth extends Component
{
    use AuthorizesPermission;

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizePermission('system.health');
    }

    public function retry(string $uuid, AuditLogger $audit): void
    {
        $this->authorizePermission('system.health');
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $audit->log('queue.job_retried', null, [], ['uuid' => $uuid]);
        $this->flash = __('admin.health.retried');
    }

    public function forget(string $uuid, AuditLogger $audit): void
    {
        $this->authorizePermission('system.health');
        Artisan::call('queue:forget', ['id' => $uuid]);
        $audit->log('queue.job_forgotten', null, [], ['uuid' => $uuid]);
        $this->flash = __('admin.health.forgotten');
    }

    public function render(SystemHealthService $health): View
    {
        return view('livewire.admin.system-health', [
            'queues' => $health->queueSizes(),
            'failedJobs' => $health->failedJobs(),
            'aiStatus' => $health->aiStatus(),
            'stats' => $health->scoringStats(),
            'errorLines' => $health->recentErrors(50),
        ])->title(__('admin.health.title'));
    }
}
