@props(['message' => null, 'error' => null])

@if ($message)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800" role="status">{{ $message }}</div>
@endif
@if ($error)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">{{ $error }}</div>
@endif
