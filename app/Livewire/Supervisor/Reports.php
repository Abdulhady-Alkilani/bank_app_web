<?php

namespace App\Livewire\Supervisor;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ComplianceReportService;
use App\Support\XlsxExport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Component
{
    use AuthorizesPermission;

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->authorizePermission('reports.view');
        $this->from = now()->subDays(29)->toDateString();
        $this->to = now()->toDateString();
    }

    public function preset(int $days): void
    {
        $this->from = now()->subDays(max(1, $days) - 1)->toDateString();
        $this->to = now()->toDateString();
    }

    public function exportPdf(ComplianceReportService $reports, AuditLogger $audit): StreamedResponse
    {
        $this->authorizePermission('reports.view');
        [$from, $to] = $this->period();

        $binary = $reports->pdf($reports->summary($from, $to));
        $audit->log('report.exported', null, [], ['report' => 'compliance', 'format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return response()->streamDownload(fn () => print ($binary), 'compliance-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function exportExcel(ComplianceReportService $reports, AuditLogger $audit): StreamedResponse
    {
        $this->authorizePermission('reports.view');
        [$from, $to] = $this->period();

        $rows = $reports->rows($reports->summary($from, $to));
        $audit->log('report.exported', null, [], ['report' => 'compliance', 'format' => 'xlsx', 'from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return XlsxExport::download('compliance-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx', $rows);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(): array
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        return [Carbon::parse($this->from), Carbon::parse($this->to)];
    }

    public function render(ComplianceReportService $reports): View
    {
        $valid = $this->from !== '' && $this->to !== '' && $this->from <= $this->to;
        $summary = $valid ? $reports->summary(Carbon::parse($this->from), Carbon::parse($this->to)) : null;

        return view('livewire.supervisor.reports', [
            'summary' => $summary,
            'rows' => $summary ? collect($reports->rows($summary))->skip(1)->groupBy(0) : collect(),
        ])->title(__('supervisor.reports.title'));
    }
}
