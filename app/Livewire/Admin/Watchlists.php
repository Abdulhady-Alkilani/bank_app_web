<?php

namespace App\Livewire\Admin;

use App\Enums\WatchlistEntity;
use App\Enums\WatchlistType;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Watchlist;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Watchlists extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $filterType = '';

    public string $filterEntity = '';

    public string $search = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public string $listType = 'blacklist';

    public string $entityType = 'device';

    public string $entityValue = '';

    public string $reason = '';

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorizePermission('lists.manage');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['filterType', 'filterEntity', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorizePermission('lists.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('lists.manage');
        $entry = Watchlist::findOrFail($id);
        $this->resetForm();
        $this->editId = $entry->id;
        $this->listType = $entry->list_type->value;
        $this->entityType = $entry->entity_type->value;
        $this->entityValue = $entry->entity_value;
        $this->reason = (string) $entry->reason;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(AuditLogger $audit): void
    {
        $this->authorizePermission('lists.manage');

        $data = $this->validate([
            'listType' => ['required', Rule::enum(WatchlistType::class)],
            'entityType' => ['required', Rule::enum(WatchlistEntity::class)],
            'entityValue' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $attributes = [
            'list_type' => $data['listType'],
            'entity_type' => $data['entityType'],
            'entity_value' => trim($data['entityValue']),
            'reason' => $data['reason'] !== '' ? $data['reason'] : null,
        ];

        if ($this->editId) {
            $entry = Watchlist::findOrFail($this->editId);
            $old = $entry->only(array_keys($attributes));
            $entry->update($attributes);
            $audit->log('watchlist.updated', $entry, $this->plain($old), $attributes);
            $this->flash = __('admin.watchlists.updated');
        } else {
            $entry = Watchlist::create($attributes + ['added_at' => now()]);
            $audit->log('watchlist.created', $entry, [], $attributes);
            $this->flash = __('admin.watchlists.created');
        }

        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $this->authorizePermission('lists.manage');
        $entry = Watchlist::findOrFail($id);
        $audit->log('watchlist.deleted', $entry, $this->plain($entry->only(['list_type', 'entity_type', 'entity_value', 'reason'])), []);
        $entry->delete();
        $this->flash = __('admin.watchlists.deleted');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $values);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editId', 'listType', 'entityType', 'entityValue', 'reason']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $entries = Watchlist::query()
            ->when($this->filterType !== '', fn ($q) => $q->where('list_type', $this->filterType))
            ->when($this->filterEntity !== '', fn ($q) => $q->where('entity_type', $this->filterEntity))
            ->when($this->search !== '', fn ($q) => $q->where('entity_value', 'like', '%'.$this->search.'%'))
            ->latest('id')->paginate(15);

        return view('livewire.admin.watchlists', [
            'entries' => $entries,
            'types' => WatchlistType::options(),
            'entities' => WatchlistEntity::options(),
        ])->title(__('admin.watchlists.title'));
    }
}
