<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Services\Audit\AuditLogger;
use App\Services\Backup\BackupService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Backups extends Component
{
    use AuthorizesPermission;

    public ?string $flash = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorizePermission('backups.manage');
    }

    public function create(BackupService $backups): void
    {
        $this->authorizePermission('backups.manage');
        $this->flash = $this->errorMessage = null;

        try {
            $file = $backups->create();
            $this->flash = __('admin.backups.created', ['file' => $file]);
        } catch (Throwable $e) {
            report($e);
            $this->errorMessage = __('admin.backups.failed');
        }
    }

    public function download(string $file, BackupService $backups, AuditLogger $audit): StreamedResponse
    {
        $this->authorizePermission('backups.manage');

        $path = $backups->path($file);
        $audit->log('backup.downloaded', null, [], ['file' => basename($path)]);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, basename($path));
    }

    public function render(BackupService $backups): View
    {
        return view('livewire.admin.backups', ['files' => $backups->list()])->title(__('admin.backups.title'));
    }
}
