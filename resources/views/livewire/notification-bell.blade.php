<div wire:poll.15s class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = ! open" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="{{ __('common.notifications') }}">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
        @if ($unread > 0)
            <span class="absolute -end-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-4 text-white" data-testid="unread-count">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition class="absolute end-0 z-50 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <p class="text-sm font-semibold">{{ __('common.notifications') }}</p>
            @if ($unread > 0)
                <button type="button" wire:click="markAllRead" class="text-xs text-brand-700 hover:underline">{{ __('analyst.notifications.mark_all') }}</button>
            @endif
        </div>
        @if ($notifications->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-slate-500">{{ __('common.no_notifications') }}</p>
        @else
            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                @foreach ($notifications as $n)
                    <li wire:key="n-{{ $n['id'] }}">
                        <button type="button" wire:click="openNotification('{{ $n['id'] }}')" class="flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-slate-50">
                            @if ($n['score'] !== null)<x-analyst.score :score="$n['score']" class="mt-0.5" />@endif
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-slate-800">{{ $n['message'] }}</span>
                                <span class="block text-xs text-slate-400">{{ $n['time'] }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
