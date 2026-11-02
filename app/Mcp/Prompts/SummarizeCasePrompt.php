<?php

namespace App\Mcp\Prompts;

use App\Mcp\Support\McpAccess;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('summarize_case')]
#[Title('Summarize a case')]
#[Description('Guides the assistant to summarize an investigation case (all its alerts) in Arabic with risk, evidence and a recommended human action. تلخيص حالة تحقيق.')]
class SummarizeCasePrompt extends FraudPrompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [new Argument('case_id', 'Investigation case id to summarize', required: true)];
    }

    protected function render(Request $request, User $user): string
    {
        $request->validate(['case_id' => 'required|integer|min:1']);
        $case = McpAccess::caseFor($user, $request->get('case_id'));

        $alertIds = $case->alerts()->orderBy('id')->pluck('id')->all();
        $list = $alertIds === [] ? 'لا توجد تنبيهات مرتبطة / no linked alerts' : implode(', ', $alertIds);
        $notes = $case->notes()->count();
        $status = $case->status->value;
        $priority = $case->priority->value;

        return <<<TEXT
        لخّص الحالة رقم {$case->id} ("{$case->title}") للمحلل البشري.
        Summarize investigation case #{$case->id} for the human analyst.

        البيانات الأساسية / Basics: الحالة {$status}، الأولوية {$priority}، عدد الملاحظات {$notes}، التنبيهات: {$list}.

        الخطوات بالترتيب / Steps:
        1. لكل تنبيه في القائمة استدعِ get_alert_details ثم explain_score (alert_id).
        2. استدعِ get_account_profile و get_transaction_history و get_device_links للحساب الرئيسي (بمعرّف أحد التنبيهات).
        3. استدعِ find_related_accounts (depth=2) لاكتشاف الشبكة.
        4. اقرأ fraud://investigation-guide لتحديد نوع السيناريو (استيلاء، بغل، جهاز مشترك).

        اكتب ملخصاً **باللغة العربية** من ثلاثة أقسام / Write an ARABIC summary with three sections:
        - **الخطورة**: أعلى درجة وقرار آلي وتقييمك العام للحالة.
        - **الأدلة**: أبرز الأدلة مرتبة زمنياً (قيم مقنّعة فقط).
        - **الإجراء الموصى به (بشري)**: خطوة تالية واحدة واضحة؛ يمكنك اقتراحها عبر propose_action أو توثيقها عبر add_case_note (تحتاجان موافقة المحلل).

        لا تنفذ أي إجراء بنفسك. القرار النهائي للمحلل.
        TEXT;
    }
}
