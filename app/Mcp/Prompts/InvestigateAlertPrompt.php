<?php

namespace App\Mcp\Prompts;

use App\Mcp\Support\McpAccess;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('investigate_alert')]
#[Title('Investigate an alert')]
#[Description('Guides the assistant through a full investigation of one alert and asks for an Arabic summary with risk, evidence and a recommended human action. التحقيق في تنبيه.')]
class InvestigateAlertPrompt extends FraudPrompt
{
    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [new Argument('alert_id', 'Alert id to investigate', required: true)];
    }

    protected function render(Request $request, User $user): string
    {
        $request->validate(['alert_id' => 'required|integer|min:1']);
        $alert = McpAccess::alertFor($user, $request->get('alert_id'));
        $id = $alert->id;

        return <<<TEXT
        أنت مساعد تحقيق في الاحتيال المصرفي يعمل مع محلل بشري. حقّق في التنبيه رقم {$id}.
        You are a fraud-investigation assistant working with a human analyst. Investigate alert #{$id}.

        اتبع الخطوات بالترتيب / Follow these steps in order:
        1. get_alert_details(alert_id={$id}) - افهم المعاملة/الحدث والدرجة والتسلسل الزمني.
        2. explain_score(alert_id={$id}) - افهم أسباب الدرجة.
        3. get_account_profile(alert_id={$id}) - قارن بسلوك العميل المعتاد.
        4. get_transaction_history(alert_id={$id}, days=30, limit=30) - ابحث عن أنماط غير عادية.
        5. get_device_links(alert_id={$id}) - تحقق من الأجهزة الجديدة أو المشتركة.
        6. find_related_accounts(alert_id={$id}, depth=2) - ابحث عن شبكة أو حسابات بغل.
        7. اقرأ المورد fraud://investigation-guide و fraud://policy عند الحاجة.

        ثم اكتب ملخصاً **باللغة العربية** يتضمن / Then write an ARABIC summary containing:
        - **مستوى الخطورة**: الدرجة والقرار الآلي ومدى ثقتك.
        - **الأدلة**: نقاط محددة مرتبطة بمعاملات وأجهزة ومستفيدين (بالقيم المقنّعة فقط).
        - **الإجراء المقترح (بشري)**: إجراء واحد واضح مع التبرير، واذكر أنه يحتاج موافقة المحلل.

        قواعد صارمة / Hard rules:
        - لا تجمّد ولا تحظر مباشرة؛ يمكنك فقط استدعاء propose_action أو add_case_note (اقتراحات بانتظار موافقة المحلل) إذا طلب المحلل ذلك.
        - لا تحاول كشف البيانات المقنّعة. القرار النهائي للمحلل.
        TEXT;
    }
}
