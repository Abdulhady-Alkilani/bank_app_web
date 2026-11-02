<?php

namespace App\Livewire\Analyst;

use App\Enums\CaseStatus;
use App\Models\InvestigationCase;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CaseIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $mine = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', InvestigationCase::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $user = auth()->user();

        $cases = InvestigationCase::query()
            ->with('assignee')
            ->withCount(['alerts', 'notes'])
            ->when(! $user->hasRole(['supervisor', 'admin']), fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('assigned_to')->orWhere('assigned_to', $user->id)))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->mine, fn (Builder $q) => $q->where('assigned_to', $user->id))
            ->orderByRaw("CASE priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->latest('id')
            ->paginate(15);

        return view('livewire.analyst.case-index', [
            'cases' => $cases,
            'statuses' => CaseStatus::options(),
        ])->title(__('analyst.cases.title'));
    }
}
