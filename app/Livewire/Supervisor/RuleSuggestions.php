<?php

namespace App\Livewire\Supervisor;

use App\Enums\RuleSuggestionStatus;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Rule;
use App\Models\RuleSuggestion;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RuleSuggestions extends Component
{
    use AuthorizesPermission;

    /** @var array<int, string> suggestion id => review note */
    public array $notes = [];

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizePermission('rule_suggestions.review');
    }

    /**
     * Accepting creates an INACTIVE rule that an admin can review and enable.
     */
    public function accept(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('rule_suggestions.review');
        $this->flash = null;

        DB::transaction(function () use ($id, $audit) {
            $suggestion = RuleSuggestion::lockForUpdate()->findOrFail($id);
            abort_unless($suggestion->status === RuleSuggestionStatus::Pending, 422, __('supervisor.suggestions.already_reviewed'));

            $rule = Rule::create([
                'rule_name' => $suggestion->title,
                'description' => $suggestion->description,
                'weight' => 10,
                'priority' => 100,
                'is_active' => false,
                'conditions' => $suggestion->conditions ?? [],
                'action' => 'flag',
            ]);

            $suggestion->update([
                'status' => RuleSuggestionStatus::Accepted,
                'rule_id' => $rule->id,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $this->note($id),
            ]);

            $audit->log('rule_suggestion.accepted', $suggestion, ['status' => 'pending'], ['status' => 'accepted', 'rule_id' => $rule->id, 'note' => $this->note($id)]);
            $audit->log('rule.created', $rule, [], ['source' => 'rule_suggestion', 'suggestion_id' => $suggestion->id, 'is_active' => false]);
        });

        $this->flash = __('supervisor.suggestions.accepted');
    }

    public function reject(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('rule_suggestions.review');
        $this->flash = null;

        if ($this->note($id) === null) {
            $this->addError('notes.'.$id, __('supervisor.suggestions.note_required'));

            return;
        }

        $suggestion = RuleSuggestion::findOrFail($id);
        abort_unless($suggestion->status === RuleSuggestionStatus::Pending, 422, __('supervisor.suggestions.already_reviewed'));

        $suggestion->update([
            'status' => RuleSuggestionStatus::Rejected,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $this->note($id),
        ]);

        $audit->log('rule_suggestion.rejected', $suggestion, ['status' => 'pending'], ['status' => 'rejected', 'note' => $this->note($id)]);
        $this->flash = __('supervisor.suggestions.rejected');
    }

    private function note(int $id): ?string
    {
        $note = trim((string) ($this->notes[$id] ?? ''));

        return $note === '' ? null : $note;
    }

    public function render(): View
    {
        return view('livewire.supervisor.rule-suggestions', [
            'pending' => RuleSuggestion::with('suggester')->where('status', RuleSuggestionStatus::Pending)->oldest()->get(),
            'reviewed' => RuleSuggestion::with(['suggester', 'reviewer'])->where('status', '!=', RuleSuggestionStatus::Pending)->latest('reviewed_at')->limit(15)->get(),
        ])->title(__('supervisor.suggestions.title'));
    }
}
