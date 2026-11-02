<?php

namespace App\Livewire\Analyst;

use App\Enums\AiProposalStatus;
use App\Models\AiProposal;
use App\Models\Alert;
use App\Services\Investigation\AiProposalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;

class Assistant extends Component
{
    public ?string $error = null;

    public function approve(int $proposalId, AiProposalService $service): void
    {
        Gate::authorize('viewAny', Alert::class);
        $this->error = null;
        $proposal = $this->proposalsQuery()->where('status', AiProposalStatus::Pending)->findOrFail($proposalId);

        try {
            $service->approve($proposal, auth()->user());
        } catch (InvalidArgumentException|AuthorizationException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function reject(int $proposalId, AiProposalService $service): void
    {
        Gate::authorize('viewAny', Alert::class);
        $this->error = null;
        $proposal = $this->proposalsQuery()->where('status', AiProposalStatus::Pending)->findOrFail($proposalId);

        try {
            $service->reject($proposal, auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Alert::class);

        return view('livewire.analyst.assistant', [
            'mcpUrl' => url('/mcp/fraud'),
            'pending' => $this->proposalsQuery()->where('status', AiProposalStatus::Pending)->with(['case', 'alert'])->latest()->get(),
            'history' => $this->proposalsQuery()->where('status', '!=', AiProposalStatus::Pending)->with(['reviewer'])->latest('reviewed_at')->limit(10)->get(),
        ])->title(__('analyst.assistant.title'));
    }

    /**
     * Proposals on cases/alerts assigned to the analyst; supervisors and admins review all of them.
     *
     * @return Builder<AiProposal>
     */
    private function proposalsQuery(): Builder
    {
        $user = auth()->user();

        return AiProposal::query()->when(
            ! $user->hasRole(['supervisor', 'admin']),
            fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('case', fn ($c) => $c->where('assigned_to', $user->id))
                ->orWhereHas('alert', fn ($a) => $a->where('assigned_to', $user->id))),
        );
    }
}
