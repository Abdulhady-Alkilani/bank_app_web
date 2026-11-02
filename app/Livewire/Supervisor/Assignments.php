<?php

namespace App\Livewire\Supervisor;

use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Alert;
use App\Models\InvestigationCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class Assignments extends Component
{
    use AuthorizesPermission, WithPagination;

    /** alerts | cases */
    public string $kind = 'alerts';

    /** unassigned | assigned */
    public string $scope = 'unassigned';

    /** @var array<int, int|string> */
    public array $selected = [];

    /** analyst id, or "0" to unassign */
    public string $target = '';

    public ?string $flash = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->authorizePermission('assignments.manage');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['kind', 'scope'], true)) {
            $this->selected = [];
            $this->resetPage();
        }
    }

    public function assign(AuditLogger $audit, SettingsService $settings): void
    {
        $this->authorizePermission('assignments.manage');
        $this->flash = $this->errorMessage = null;

        if ($this->selected === [] || $this->target === '') {
            $this->errorMessage = __('supervisor.assignments.select_first');

            return;
        }

        $analyst = null;
        if ($this->target !== '0') {
            $analyst = $this->analysts()->firstWhere('id', (int) $this->target);
            if (! $analyst) {
                $this->errorMessage = __('supervisor.assignments.invalid_analyst');

                return;
            }
        }

        $ids = array_map('intval', $this->selected);
        $models = $this->kind === 'cases'
            ? InvestigationCase::whereIn('id', $ids)->get()
            : Alert::whereIn('id', $ids)->get();

        foreach ($models as $model) {
            $old = $model->assigned_to;
            $model->update(['assigned_to' => $analyst?->id]);
            $audit->log($analyst ? 'assignment.assigned' : 'assignment.unassigned', $model, ['assigned_to' => $old], ['assigned_to' => $analyst?->id]);
        }

        $this->flash = __('supervisor.assignments.done', ['count' => $models->count()]);

        if ($analyst) {
            $capacity = (int) $settings->get('alerts.analyst_daily_capacity', 50);
            if ($this->load($analyst->id) > $capacity) {
                $this->flash .= ' '.__('supervisor.assignments.over_capacity', ['name' => $analyst->name]);
            }
        }

        $this->selected = [];
    }

    private function load(int $analystId): int
    {
        return Alert::where('assigned_to', $analystId)->where('status', '!=', AlertStatus::Closed->value)->count();
    }

    /**
     * @return Collection<int, User>
     */
    private function analysts()
    {
        return User::query()->where('is_active', true)->with('roles')->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasRole('analyst'))->values();
    }

    public function render(SettingsService $settings): View
    {
        $capacity = max(1, (int) $settings->get('alerts.analyst_daily_capacity', 50));

        $items = $this->kind === 'cases'
            ? InvestigationCase::query()->with('assignee')->where('status', '!=', CaseStatus::Closed->value)
                ->when($this->scope === 'unassigned', fn (Builder $q) => $q->whereNull('assigned_to'), fn (Builder $q) => $q->whereNotNull('assigned_to'))
                ->latest('id')->paginate(15)
            : Alert::query()->with(['assignee', 'riskScore', 'case'])->where('status', '!=', AlertStatus::Closed->value)
                ->when($this->scope === 'unassigned', fn (Builder $q) => $q->whereNull('assigned_to'), fn (Builder $q) => $q->whereNotNull('assigned_to'))
                ->latest('id')->paginate(15);

        $analysts = $this->analysts()->map(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name, 'load' => $this->load($u->id),
        ]);

        return view('livewire.supervisor.assignments', [
            'items' => $items,
            'analysts' => $analysts,
            'capacity' => $capacity,
        ])->title(__('supervisor.assignments.title'));
    }
}
