<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 11pt; color: #1e293b; direction: {{ $rtl ? 'rtl' : 'ltr' }}; text-align: {{ $rtl ? 'right' : 'left' }}; }
        h1 { font-size: 18pt; margin-bottom: 2pt; }
        .period { color: #64748b; margin-bottom: 12pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        th { background: #f1f5f9; text-align: {{ $rtl ? 'right' : 'left' }}; padding: 5pt; border: 1px solid #cbd5e1; }
        td { padding: 5pt; border: 1px solid #e2e8f0; }
        h2 { font-size: 13pt; margin: 14pt 0 4pt; color: #0f172a; }
    </style>
</head>
<body>
    <h1>{{ __('supervisor.reports.title') }}</h1>
    <p class="period">{{ __('supervisor.reports.period') }}: {{ $s['from'] }} — {{ $s['to'] }}</p>

    @php $sections = collect($rows)->skip(1)->groupBy(0); @endphp
    @foreach ($sections as $section => $items)
        <h2>{{ $section }}</h2>
        <table>
            <thead><tr><th>{{ __('supervisor.reports.metric') }}</th><th>{{ __('supervisor.reports.value') }}</th></tr></thead>
            <tbody>
                @foreach ($items as $row)
                    <tr><td>{{ $row[1] }}</td><td>{{ $row[2] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <p class="period">{{ __('supervisor.reports.generated_at', ['time' => now()->format('Y-m-d H:i')]) }}</p>
</body>
</html>
