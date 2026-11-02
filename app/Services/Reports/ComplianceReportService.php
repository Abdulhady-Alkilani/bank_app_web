<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use Mpdf\Mpdf;

class ComplianceReportService
{
    public function __construct(private readonly SupervisorStats $stats) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        return $this->stats->summary($from, $to);
    }

    /**
     * Flat rows for the Excel export: [section, metric, value].
     *
     * @param  array<string, mixed>  $s
     * @return array<int, array<int, scalar|null>>
     */
    public function rows(array $s): array
    {
        $rows = [[__('supervisor.reports.section'), __('supervisor.reports.metric'), __('supervisor.reports.value')]];
        $add = function (string $section, string $metric, mixed $value) use (&$rows) {
            $rows[] = [$section, $metric, $value ?? '—'];
        };

        $sec = __('supervisor.reports.sections.overview');
        $add($sec, __('supervisor.reports.period'), $s['from'].' → '.$s['to']);
        $add($sec, __('supervisor.reports.alerts_total'), $s['alerts_total']);
        $add($sec, __('supervisor.reports.cases_total'), $s['cases_total']);
        $add($sec, __('supervisor.reports.decided_total'), $s['decided_total']);
        $add($sec, __('supervisor.reports.avg_handling'), $s['avg_handling_minutes']);
        $add($sec, __('supervisor.reports.false_positive_rate'), $s['false_positive_rate'] === null ? null : $s['false_positive_rate'].'%');
        $add($sec, __('supervisor.reports.blocked_amount'), $s['blocked_amount']);
        $add($sec, __('supervisor.reports.blocked_count'), $s['blocked_count']);

        foreach ($s['alerts_by_status'] as $k => $v) {
            $add(__('supervisor.reports.sections.alerts_by_status'), __('enums.AlertStatus.'.$k), $v);
        }
        foreach ($s['alerts_by_priority'] as $k => $v) {
            $add(__('supervisor.reports.sections.alerts_by_priority'), __('enums.Priority.'.$k), $v);
        }
        foreach ($s['cases_by_status'] as $k => $v) {
            $add(__('supervisor.reports.sections.cases_by_status'), __('enums.CaseStatus.'.$k), $v);
        }
        foreach ($s['decisions'] as $k => $v) {
            $add(__('supervisor.reports.sections.decisions'), $k === 'none' ? __('supervisor.dashboard.undecided') : __('enums.AnalystDecision.'.$k), $v);
        }
        foreach ($s['actions_by_type'] as $k => $v) {
            $add(__('supervisor.reports.sections.actions'), __('enums.AccountActionType.'.$k), $v);
        }
        foreach ($s['approvals'] as $k => $v) {
            $add(__('supervisor.reports.sections.approvals'), __('enums.ApprovalStatus.'.$k), $v);
        }

        return $rows;
    }

    /**
     * Renders the report as a PDF binary string (Arabic RTL aware).
     *
     * @param  array<string, mixed>  $s
     */
    public function pdf(array $s): string
    {
        $rtl = app()->getLocale() === 'ar';
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoArabic' => true,
        ]);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $mpdf->SetTitle(__('supervisor.reports.title'));
        $mpdf->WriteHTML(view('reports.compliance', ['s' => $s, 'rtl' => $rtl, 'rows' => $this->rows($s)])->render());

        return $mpdf->Output('', 'S');
    }
}
