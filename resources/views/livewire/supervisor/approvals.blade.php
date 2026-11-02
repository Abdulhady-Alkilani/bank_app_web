<div class="space-y-6" wire:poll.30s>
    <x-console.flash :message="$flash" :error="$errorMessage" />

    <x-ui.card :title="__('supervisor.approvals.pending')" :padded="false">
        <x-ui.table class="rounded-none border-0 shadow-none">
            <x-slot:head>
                <th>{{ __('supervisor.approvals.type') }}</th><th>{{ __('supervisor.approvals.subject') }}</th><th>{{ __('supervisor.approvals.requested_by') }}</th>
                <th>{{ __('supervisor.approvals.reason') }}</th><th>{{ __('supervisor.approvals.requested_at') }}</th><th>{{ __('common.actions') }}</th>
            </x-slot:head>
            @forelse ($pending as $approval)
                <tr wire:key="approval-{{ $approval->id }}">
                    <td>{{ $approval->type->label() }}</td>
                    <td>{{ $this->describe($approval) }}</td>
                    <td>{{ $approval->requester?->name }}</td>
                    <td class="max-w-xs">{{ $approval->reason }}</td>
                    <td dir="ltr" class="whitespace-nowrap text-start">{{ $approval->created_at?->format('Y-m-d H:i') }}</td>
                    <td>
                        @if ($rejectingId === $approval->id)
                            <form wire:submit="reject" class="space-y-2">
                                <x-ui.input name="rejectReason" wire:model="rejectReason" :placeholder="__('supervisor.approvals.reject_reason')" />
                                <div class="flex gap-2">
                                    <x-ui.button type="submit" variant="danger">{{ __('supervisor.approvals.confirm_reject') }}</x-ui.button>
                                    <x-ui.button variant="ghost" wire:click="cancelReject">{{ __('common.cancel') }}</x-ui.button>
                                </div>
                            </form>
                        @else
                            <div class="flex gap-2">
                                <x-ui.button wire:click="approve({{ $approval->id }})">{{ __('supervisor.approvals.approve') }}</x-ui.button>
                                <x-ui.button variant="secondary" wire:click="startReject({{ $approval->id }})">{{ __('supervisor.approvals.reject') }}</x-ui.button>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state :title="__('supervisor.approvals.none')" /></td></tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>

    @if ($recent->isNotEmpty())
        <x-ui.card :title="__('supervisor.approvals.recent')" :padded="false">
            <x-ui.table class="rounded-none border-0 shadow-none">
                <x-slot:head>
                    <th>{{ __('supervisor.approvals.type') }}</th><th>{{ __('common.status') }}</th><th>{{ __('supervisor.approvals.requested_by') }}</th><th>{{ __('supervisor.approvals.decided_by') }}</th><th>{{ __('supervisor.approvals.decided_at') }}</th>
                </x-slot:head>
                @foreach ($recent as $approval)
                    <tr wire:key="recent-{{ $approval->id }}">
                        <td>{{ $approval->type->label() }}</td>
                        <td><x-ui.badge :color="$approval->status === \App\Enums\ApprovalStatus::Approved ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'" :label="$approval->status->label()" /></td>
                        <td>{{ $approval->requester?->name }}</td>
                        <td>{{ $approval->approver?->name ?? '—' }}</td>
                        <td dir="ltr" class="text-start">{{ $approval->decided_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
