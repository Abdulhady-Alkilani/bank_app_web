<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #1e293b; direction: {{ $rtl ? 'rtl' : 'ltr' }}; text-align: {{ $rtl ? 'right' : 'left' }}; }
        h1 { font-size: 16pt; margin: 0 0 4px; }
        h2 { font-size: 12pt; margin: 16px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; color: #312e81; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 9pt; text-align: {{ $rtl ? 'right' : 'left' }}; }
        th { background: #f1f5f9; }
        .muted { color: #64748b; font-size: 9pt; }
    </style>
</head>
<body>
<h1>{{ __('analyst.pdf.title', ['id' => $case->id]) }} — {{ $case->title }}</h1>
<p class="muted">{{ __('analyst.pdf.generated', ['date' => now()->format('Y-m-d H:i'), 'user' => $generatedBy?->name]) }}</p>

<h2>{{ __('analyst.pdf.summary') }}</h2>
<table>
    <tr><th>{{ __('analyst.cases.status') }}</th><td>{{ $case->status->label() }}</td><th>{{ __('analyst.queue.priority') }}</th><td>{{ $case->priority->label() }}</td></tr>
    <tr><th>{{ __('analyst.cases.assignee') }}</th><td>{{ $case->assignee?->name ?? '—' }}</td><th>{{ __('analyst.cases.total_amount') }}</th><td>{{ number_format($total, 2) }}</td></tr>
    <tr><th>{{ __('analyst.cases.opened_at') }}</th><td>{{ $case->created_at?->format('Y-m-d H:i') }}</td><th>{{ __('analyst.cases.alerts') }}</th><td>{{ $case->alerts->count() }}</td></tr>
</table>

<h2>{{ __('analyst.cases.alerts') }}</h2>
<table>
    <tr>
        <th>#</th><th>{{ __('analyst.queue.account') }}</th><th>{{ __('analyst.queue.score') }}</th><th>{{ __('analyst.queue.amount') }}</th>
        <th>{{ __('analyst.queue.priority') }}</th><th>{{ __('analyst.alert.system_decision') }}</th><th>{{ __('analyst.alert.analyst_decision') }}</th><th>{{ __('analyst.alert.decision_reason') }}</th>
    </tr>
    @foreach ($case->alerts as $alert)
        @php $account = $insights->account($alert); @endphp
        <tr>
            <td>{{ $alert->id }}</td>
            <td>{{ $account?->account_number ?? '—' }}</td>
            <td>{{ $alert->riskScore?->score }}</td>
            <td>{{ $alert->riskScore?->transaction ? number_format((float) $alert->riskScore->transaction->amount, 2) : '—' }}</td>
            <td>{{ $alert->priority->label() }}</td>
            <td>{{ $alert->riskScore?->decision?->label() }}</td>
            <td>{{ $alert->decision?->label() ?? '—' }}</td>
            <td>{{ $alert->decision_reason ?? '—' }}</td>
        </tr>
        @foreach ($insights->reasons($alert->riskScore) as $reason)
            <tr><td></td><td colspan="7" class="muted">• {{ $reason['text'] }}</td></tr>
        @endforeach
    @endforeach
</table>

<h2>{{ __('analyst.cases.notes') }}</h2>
@forelse ($case->notes as $note)
    <p><strong>{{ $note->user?->name ?? '—' }}</strong>@if ($note->is_ai_generated) ({{ __('analyst.cases.ai_note') }})@endif
        <span class="muted">{{ $note->created_at?->format('Y-m-d H:i') }}</span><br>{{ $note->note }}</p>
@empty
    <p class="muted">{{ __('analyst.cases.no_notes') }}</p>
@endforelse

<h2>{{ __('analyst.pdf.actions') }}</h2>
@if ($actions->isEmpty())
    <p class="muted">{{ __('analyst.pdf.no_actions') }}</p>
@else
    <table>
        <tr><th>{{ __('analyst.queue.account') }}</th><th>{{ __('analyst.pdf.action') }}</th><th>{{ __('analyst.cases.status') }}</th><th>{{ __('analyst.pdf.requested_by') }}</th><th>{{ __('analyst.alert.decision_reason') }}</th></tr>
        @foreach ($actions as $action)
            <tr>
                <td>{{ $action->account?->account_number }}</td>
                <td>{{ $action->action->label() }}</td>
                <td>{{ $action->status->label() }}</td>
                <td>{{ $action->requester?->name ?? '—' }}</td>
                <td>{{ $action->reason }}</td>
            </tr>
        @endforeach
    </table>
@endif

<h2>{{ __('analyst.cases.attachments') }}</h2>
@forelse ($case->attachments as $attachment)
    <p>{{ $attachment->file_name }} <span class="muted">({{ number_format($attachment->file_size / 1024, 1) }} KB)</span></p>
@empty
    <p class="muted">{{ __('analyst.cases.no_attachments') }}</p>
@endforelse
</body>
</html>
