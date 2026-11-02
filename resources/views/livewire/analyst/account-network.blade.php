<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        @if ($account->customer_id)
            <x-ui.button variant="ghost" :href="route('analyst.customers.show', $account->customer_id)" wire:navigate>&larr; {{ __('analyst.network.customer') }}</x-ui.button>
        @endif
        <h2 class="text-lg font-semibold">{{ __('analyst.network.heading', ['account' => $account->account_number]) }}</h2>
        <div class="ms-auto flex items-center gap-2">
            <label for="depth" class="text-sm text-slate-600">{{ __('analyst.network.depth') }}</label>
            <select id="depth" wire:model.live="depth" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm">
                @foreach ([1, 2, 3] as $level)
                    <option value="{{ $level }}">{{ $level }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 text-xs text-slate-600">
        <span class="flex items-center gap-1"><i class="size-3 rounded-full bg-indigo-500"></i>{{ __('analyst.network.legend.account') }}</span>
        <span class="flex items-center gap-1"><i class="size-3 rounded-full bg-violet-500"></i>{{ __('analyst.network.legend.device') }}</span>
        <span class="flex items-center gap-1"><i class="size-3 rounded-full bg-slate-500"></i>{{ __('analyst.network.legend.ip') }}</span>
        <span class="flex items-center gap-1"><i class="size-3 rounded-full bg-teal-500"></i>{{ __('analyst.network.legend.beneficiary') }}</span>
        <span class="flex items-center gap-1"><i class="size-3 rounded-full border-2 border-red-600 bg-white"></i>{{ __('analyst.network.legend.high_risk') }}</span>
    </div>

    <div wire:ignore
         x-data="{

            elements: @js($elements),
            colors: { account: '#6366f1', device: '#8b5cf6', ip: '#64748b', beneficiary: '#14b8a6' },
            init() {
                if (! window.cytoscape) { return; }
                const cy = this.$refs.canvas._cy = window.cytoscape({
                    container: this.$refs.canvas,
                    elements: this.elements,
                    wheelSensitivity: 0.3,
                    style: [
                        { selector: 'node', style: {
                            'label': 'data(label)', 'font-size': 10, 'text-valign': 'bottom', 'text-margin-y': 4, 'color': '#334155',
                            'background-color': (n) => this.colors[n.data('type')] || '#94a3b8', 'width': 28, 'height': 28,
                            'border-width': 2, 'border-color': '#ffffff'
                        } },
                        { selector: 'node[type = &quot;account&quot;]', style: { 'shape': 'round-rectangle', 'width': 34, 'height': 34 } },
                        { selector: 'node[?root]', style: { 'width': 46, 'height': 46, 'border-color': '#312e81', 'border-width': 4, 'font-weight': 'bold' } },
                        { selector: 'node[risk >= 75]', style: { 'border-color': '#dc2626', 'border-width': 4 } },
                        { selector: 'node[risk >= 40][risk < 75]', style: { 'border-color': '#d97706', 'border-width': 3 } },
                        { selector: 'node[status = &quot;frozen&quot;]', style: { 'border-style': 'dashed' } },
                        { selector: 'edge', style: { 'width': 1.5, 'line-color': '#cbd5e1', 'target-arrow-color': '#cbd5e1', 'target-arrow-shape': 'triangle', 'curve-style': 'bezier', 'label': 'data(label)', 'font-size': 8, 'color': '#94a3b8' } },
                    ],
                    layout: { name: 'cose', animate: false, padding: 30 },
                });
                cy.on('tap', 'node', (evt) => { this.selected = evt.target.data(); });
            },
            selected: null,
            refresh(elements) {
                const cy = this.$refs.canvas._cy; if (! cy) { return; }
                cy.elements().remove();
                cy.add(elements);
                cy.layout({ name: 'cose', animate: false, padding: 30 }).run();
            },
         }"
         x-on:network-updated.window="refresh($event.detail.elements)"
         class="relative">
        <div x-ref="canvas" class="h-[28rem] w-full rounded-xl border border-slate-200 bg-white shadow-sm lg:h-[34rem]" dir="ltr"></div>
        <div x-show="selected" x-cloak class="absolute end-3 top-3 max-w-xs rounded-lg border border-slate-200 bg-white p-3 text-xs shadow">
            <p class="font-semibold" x-text="selected && selected.label"></p>
            <p class="text-slate-500" x-text="selected && selected.type"></p>
            <p x-show="selected && selected.risk !== undefined" class="text-slate-500">{{ __('analyst.network.risk') }}: <span x-text="selected && selected.risk"></span></p>
        </div>
    </div>

    <x-ui.card :title="__('analyst.network.shared_devices')">
        @if ($sharedDevices->isEmpty())
            <p class="text-sm text-slate-500">{{ __('analyst.network.no_shared') }}</p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach ($sharedDevices as $row)
                    <li wire:key="sd-{{ $row['device']->id }}">
                        <span class="font-mono text-xs">{{ substr($row['device']->device_fingerprint, 0, 16) }}</span>
                        → {{ $row['accounts']->pluck('account_number')->implode(', ') }}
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
