@foreach ($frozen as $frozenAccount)
    <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800" role="alert">
        <svg class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
        <div class="text-sm">
            <p class="font-semibold">{{ __('customer.frozen.title', ['account' => $frozenAccount->account_number]) }}</p>
            <p class="mt-0.5">{{ __('customer.frozen.body') }}</p>
        </div>
    </div>
@endforeach
