<?php

namespace App\Http\Controllers\Analyst;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountAction;
use App\Models\InvestigationCase;
use App\Services\Audit\AuditLogger;
use App\Services\Investigation\AlertInsights;
use App\Services\Investigation\CaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class CaseReportController extends Controller
{
    public function pdf(Request $request, InvestigationCase $case, AuditLogger $audit, AlertInsights $insights, CaseService $cases): Response
    {
        Gate::authorize('view', $case);

        $case->load(['assignee', 'notes.user', 'attachments', 'alerts.riskScore.transaction.beneficiary', 'alerts.riskScore.event', 'alerts.decider']);

        $accountIds = $case->alerts->map(fn ($alert) => $insights->account($alert)?->id)->filter()->unique();
        $actions = AccountAction::query()
            ->where(fn ($q) => $q->whereIn('alert_id', $case->alerts->pluck('id'))->orWhereIn('account_id', $accountIds))
            ->with(['account', 'requester'])
            ->latest()
            ->get();

        $rtl = app()->getLocale() === 'ar';

        $html = view('analyst.case-pdf', [
            'case' => $case,
            'rtl' => $rtl,
            'actions' => $actions,
            'accounts' => Account::query()->whereIn('id', $accountIds)->get()->keyBy('id'),
            'insights' => $insights,
            'total' => $cases->totalAmount($case),
            'generatedBy' => $request->user(),
        ])->render();

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'directionality' => $rtl ? 'rtl' : 'ltr',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'dejavusans',
        ]);
        $mpdf->SetTitle('Case #'.$case->id);
        $mpdf->WriteHTML($html);

        $audit->log('case.report_exported', $case, [], ['format' => 'pdf'], $request->user());

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="case-'.$case->id.'.pdf"',
        ]);
    }
}
