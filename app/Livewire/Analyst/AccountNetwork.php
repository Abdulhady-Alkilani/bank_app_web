<?php

namespace App\Livewire\Analyst;

use App\Models\Account;
use App\Models\Alert;
use App\Services\Graph\GraphService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class AccountNetwork extends Component
{
    public Account $account;

    #[Url(except: 2)]
    public int $depth = 2;

    public function mount(Account $account): void
    {
        Gate::authorize('viewAny', Alert::class);

        $this->account = $account;
        $this->depth = $this->clampDepth($this->depth);
    }

    public function updatedDepth(GraphService $graph): void
    {
        $this->depth = $this->clampDepth($this->depth);

        // The graph container is wire:ignore'd, so push new elements to Alpine instead of re-rendering it.
        $this->dispatch('network-updated', elements: $graph->networkForAccount($this->account, $this->depth));
    }

    public function render(GraphService $graph): View
    {
        $elements = $graph->networkForAccount($this->account, $this->depth);

        return view('livewire.analyst.account-network', [
            'elements' => $elements,
            'sharedDevices' => $graph->sharedDevices($this->account),
        ])->title(__('analyst.network.title'));
    }

    private function clampDepth(int $depth): int
    {
        return max(1, min(3, $depth));
    }
}
