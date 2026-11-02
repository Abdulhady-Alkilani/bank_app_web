<?php

namespace App\Livewire\Analyst;

use App\Enums\AlertStatus;
use App\Enums\Priority;
use App\Models\Alert;
use App\Models\Transaction;
use App\Services\Investigation\AlertQueue as AlertQueueQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AlertQueue extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: '')]
    public string $minScore = '';

    #[Url(except: '')]
    public string $maxScore = '';

    #[Url(except: '')]
    public string $channel = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    #[Url(except: '')]
    public string $minAmount = '';

    #[Url(except: '')]
    public string $maxAmount = '';

    #[Url(except: false)]
    public bool $mine = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Alert::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['status', 'priority', 'minScore', 'maxScore', 'channel', 'dateFrom', 'dateTo', 'minAmount', 'maxAmount', 'mine']);
        $this->resetPage();
    }

    public function claim(int $alertId): void
    {
        $alert = Alert::query()->findOrFail($alertId);
        Gate::authorize('decide', $alert);

        if ($alert->assigned_to === null) {
            $alert->update(['assigned_to' => auth()->id(), 'status' => $alert->status === AlertStatus::Open ? AlertStatus::InReview : $alert->status]);
        }
    }

    public function render(AlertQueueQuery $queue): View
    {
        $alerts = $queue->query(auth()->user(), [
            'status' => $this->status,
            'priority' => $this->priority,
            'min_score' => $this->minScore,
            'max_score' => $this->maxScore,
            'channel' => $this->channel,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'min_amount' => $this->minAmount,
            'max_amount' => $this->maxAmount,
            'mine' => $this->mine,
        ])->paginate(15);

        return view('livewire.analyst.alert-queue', [
            'alerts' => $alerts,
            'statuses' => AlertStatus::options(),
            'priorities' => Priority::options(),
            'channels' => Transaction::query()->distinct()->orderBy('channel')->pluck('channel'),
        ])->title(__('analyst.queue.title'));
    }
}
