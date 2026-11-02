<?php

namespace App\Livewire\Admin;

use App\Enums\EventType;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Rule;
use App\Services\Audit\AuditLogger;
use App\Support\RuleConditions;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Rules extends Component
{
    use AuthorizesPermission;

    public bool $showForm = false;

    public ?int $editId = null;

    public string $ruleName = '';

    public string $description = '';

    public int|string $weight = 20;

    public int|string $priority = 100;

    public bool $isActive = true;

    public string $action = 'flag';

    /** @var array<int, string> */
    public array $eventTypes = [];

    public string $logic = 'all';

    /** @var array<int, array{feature: string, op: string, value: string}> */
    public array $rows = [];

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizePermission('rules.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('rules.manage');
        $this->resetForm();
        $this->rows = [$this->blankRow()];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('rules.manage');
        $rule = Rule::findOrFail($id);
        $this->resetForm();

        $conditions = $rule->conditions ?? [];
        $this->editId = $rule->id;
        $this->ruleName = $rule->rule_name;
        $this->description = (string) $rule->description;
        $this->weight = $rule->weight;
        $this->priority = $rule->priority;
        $this->isActive = $rule->is_active;
        $this->action = $rule->action;
        $this->eventTypes = array_values($conditions['event_types'] ?? []);
        $this->logic = array_key_exists('any', $conditions) ? 'any' : 'all';
        $this->rows = collect($conditions[$this->logic] ?? [])->map(fn (array $row) => [
            'feature' => (string) ($row['feature'] ?? ''),
            'op' => (string) ($row['op'] ?? '=='),
            'value' => is_array($row['value'] ?? null) ? implode(',', $row['value']) : (string) ($row['value'] ?? ''),
        ])->values()->all() ?: [$this->blankRow()];
        $this->showForm = true;
    }

    public function addRow(): void
    {
        $this->rows[] = $this->blankRow();
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizePermission('rules.manage');

        $this->validate([
            'ruleName' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'weight' => ['required', 'integer', 'min:0', 'max:100'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'],
            'action' => ['required', 'in:'.implode(',', RuleConditions::ACTIONS)],
            'eventTypes' => ['array'],
            'eventTypes.*' => ['in:'.implode(',', EventType::values())],
            'logic' => ['required', 'in:all,any'],
        ]);

        $conditions = $this->builtConditions();
        $errors = RuleConditions::validate($conditions);
        if ($errors !== []) {
            $this->addError('conditions', implode(' ', $errors));

            return;
        }

        $attributes = [
            'rule_name' => $this->ruleName,
            'description' => $this->description !== '' ? $this->description : null,
            'weight' => (int) $this->weight,
            'priority' => (int) $this->priority,
            'is_active' => $this->isActive,
            'action' => $this->action,
            'conditions' => $conditions,
        ];

        if ($this->editId) {
            $rule = Rule::findOrFail($this->editId);
            $old = $rule->only(array_keys($attributes));
            $rule->update($attributes);
            $audit->log('rule.updated', $rule, $old, $attributes);
            $this->flash = __('admin.rules.updated');
        } else {
            $rule = Rule::create($attributes);
            $audit->log('rule.created', $rule, [], $attributes);
            $this->flash = __('admin.rules.created');
        }

        $this->resetForm();
    }

    public function toggle(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('rules.manage');
        $rule = Rule::findOrFail($id);
        $rule->update(['is_active' => ! $rule->is_active]);
        $audit->log('rule.toggled', $rule, ['is_active' => ! $rule->is_active], ['is_active' => $rule->is_active]);
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('rules.manage');
        $rule = Rule::findOrFail($id);
        $audit->log('rule.deleted', $rule, $rule->only(['rule_name', 'weight', 'priority', 'is_active', 'action', 'conditions']), []);
        $rule->delete();
        $this->flash = __('admin.rules.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function builtConditions(): array
    {
        return RuleConditions::build($this->logic, $this->rows, $this->eventTypes);
    }

    /**
     * @return array{feature: string, op: string, value: string}
     */
    private function blankRow(): array
    {
        return ['feature' => RuleConditions::FEATURES[0], 'op' => '>=', 'value' => ''];
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editId', 'ruleName', 'description', 'weight', 'priority', 'isActive', 'action', 'eventTypes', 'logic', 'rows']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $preview = $this->showForm
            ? json_encode($this->builtConditions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';

        return view('livewire.admin.rules', [
            'rules' => Rule::orderBy('priority')->orderBy('id')->get(),
            'preview' => $preview,
            'previewErrors' => $this->showForm ? RuleConditions::validate($this->builtConditions()) : [],
            'features' => RuleConditions::FEATURES,
            'operators' => RuleConditions::OPERATORS,
            'actions' => RuleConditions::ACTIONS,
            'eventOptions' => EventType::options(),
        ])->title(__('admin.rules.title'));
    }
}
