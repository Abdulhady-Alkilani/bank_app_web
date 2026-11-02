<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\AuditLog as AuditLogModel;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\XlsxExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit log viewer. Used by admin (admin.audit) and, read-only, by supervisors (supervisor.audit).
 */
class AuditLog extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $userId = '';

    public string $action = '';

    public string $entityType = '';

    public string $from = '';

    public string $to = '';

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->authorizePermission('audit.view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['userId', 'action', 'entityType', 'from', 'to'], true)) {
            $this->resetPage();
            $this->selectedId = null;
        }
    }

    public function select(int $id): void
    {
        $this->authorizePermission('audit.view');
        $this->selectedId = $this->selectedId === $id ? null : $id;
    }

    public function exportCsv(AuditLogger $audit): StreamedResponse
    {
        $this->authorizePermission('audit.export');
        $audit->log('audit.exported', null, [], ['format' => 'csv', 'filters' => $this->filters()]);

        return XlsxExport::csv('audit-log-'.now()->format('Ymd-His').'.csv', $this->exportRows());
    }

    public function exportExcel(AuditLogger $audit): StreamedResponse
    {
        $this->authorizePermission('audit.export');
        $audit->log('audit.exported', null, [], ['format' => 'xlsx', 'filters' => $this->filters()]);

        return XlsxExport::download('audit-log-'.now()->format('Ymd-His').'.xlsx', $this->exportRows());
    }

    /**
     * @return array<string, string>
     */
    private function filters(): array
    {
        return array_filter([
            'user_id' => $this->userId, 'action' => $this->action, 'entity_type' => $this->entityType,
            'from' => $this->from, 'to' => $this->to,
        ], fn ($v) => $v !== '');
    }

    private function query(): Builder
    {
        return AuditLogModel::query()->with('user')
            ->when($this->userId !== '', fn (Builder $q) => $q->where('user_id', $this->userId))
            ->when($this->action !== '', fn (Builder $q) => $q->where('action', 'like', '%'.$this->action.'%'))
            ->when($this->entityType !== '', fn (Builder $q) => $q->where('entity_type', $this->entityType))
            ->when($this->from !== '', fn (Builder $q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn (Builder $q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id');
    }

    /**
     * @return \Generator<int, array<int, mixed>>
     */
    private function exportRows(): \Generator
    {
        yield ['ID', 'Time', 'User', 'Action', 'Entity type', 'Entity ID', 'Old values', 'New values', 'IP'];

        foreach ($this->query()->limit(50000)->cursor() as $log) {
            yield [
                $log->id, $log->created_at?->toDateTimeString(), $log->user?->name ?? '', $log->action,
                $log->entity_type ?? '', $log->entity_id ?? '',
                json_encode($log->old_values ?? [], JSON_UNESCAPED_UNICODE), json_encode($log->new_values ?? [], JSON_UNESCAPED_UNICODE),
                $log->ip_address ?? '',
            ];
        }
    }

    public function render(): View
    {
        $selected = $this->selectedId ? AuditLogModel::with('user')->find($this->selectedId) : null;
        $diff = [];
        if ($selected) {
            $old = (array) ($selected->old_values ?? []);
            $new = (array) ($selected->new_values ?? []);
            foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
                $diff[$key] = ['old' => $old[$key] ?? null, 'new' => $new[$key] ?? null];
            }
        }

        return view('livewire.admin.audit-log', [
            'logs' => $this->query()->paginate(20),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'entityTypes' => AuditLogModel::query()->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'selected' => $selected,
            'diff' => $diff,
            'canExport' => auth()->user()?->hasPermission('audit.export'),
        ])->title(__('admin.audit.title'));
    }
}
