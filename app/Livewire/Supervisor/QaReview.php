<?php

namespace App\Livewire\Supervisor;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Quality review: a random sample (setting qa.sample_rate) of decided alerts is drawn for the chosen period;
 * the supervisor marks each decision agree / disagree. Reviews are stored in the audit log ('qa.review').
 */
class QaReview extends Component
{
    use AuthorizesPermission;

    public string $from = '';

    public string $to = '';

    /** @var array<int, int> */
    public array $sampleIds = [];

    /** @var array<int, string> alert id => comment */
    public array $comments = [];

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizePermission('qa.review');
        $this->from = now()->subDays(7)->toDateString();
        $this->to = now()->toDateString();
    }

    public function draw(SettingsService $settings): void
    {
        $this->authorizePermission('qa.review');
        $this->flash = null;
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        $query = Alert::query()->whereNotNull('decided_at')
            ->whereBetween('decided_at', [$this->from.' 00:00:00', $this->to.' 23:59:59']);

        $total = (clone $query)->count();
        $rate = min(1.0, max(0.0, (float) $settings->get('qa.sample_rate', 0.1)));
        $size = $total === 0 ? 0 : max(1, (int) ceil($total * $rate));

        $this->sampleIds = $query->inRandomOrder()->limit($size)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->comments = [];

        $this->flash = __('supervisor.qa.drawn', ['sample' => count($this->sampleIds), 'total' => $total]);
    }

    public function review(int $alertId, string $verdict, AuditLogger $audit): void
    {
        $this->authorizePermission('qa.review');
        abort_unless(in_array($verdict, ['agree', 'disagree'], true), 422);
        abort_unless(in_array($alertId, $this->sampleIds, true), 403);

        $comment = trim((string) ($this->comments[$alertId] ?? ''));
        if ($verdict === 'disagree' && $comment === '') {
            $this->addError('comments.'.$alertId, __('supervisor.qa.comment_required'));

            return;
        }

        $alert = Alert::findOrFail($alertId);
        $audit->log('qa.review', $alert, [], [
            'verdict' => $verdict,
            'comment' => $comment,
            'original_decision' => $alert->decision?->value,
            'original_decider' => $alert->decided_by,
        ]);

        $this->resetValidation('comments.'.$alertId);
        $this->flash = __('supervisor.qa.saved');
    }

    public function render(): View
    {
        $alerts = Alert::query()->with(['decider', 'riskScore'])->whereIn('id', $this->sampleIds)->get();

        $reviews = AuditLog::query()->where('action', 'qa.review')->where('entity_type', (new Alert)->getMorphClass())
            ->whereIn('entity_id', $this->sampleIds)->latest('id')->get()->unique('entity_id')->keyBy('entity_id');

        return view('livewire.supervisor.qa-review', [
            'alerts' => $alerts,
            'reviews' => $reviews,
            'rate' => (float) app(SettingsService::class)->get('qa.sample_rate', 0.1),
        ])->title(__('supervisor.qa.title'));
    }
}
