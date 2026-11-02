# سياسة مكافحة الاحتيال / Fraud Policy

## 1. حدود القرار الآلي / Automated decision thresholds

| درجة الخطورة / Score | القرار / Decision | الإجراء / Effect |
|---|---|---|
| 0 - {{allow_max}} | allow (سماح) | تنفيذ المعاملة / transaction proceeds |
| {{challenge_min}} - {{challenge_max}} | challenge (تحقق) | رمز OTP ثم تنبيه / OTP challenge + alert |
| {{block_min}} - 100 | block (حظر) | إيقاف المعاملة وتجميد مؤقت + تنبيه / stop, temporary freeze + alert |

- الدرجة النهائية = {{rule_weight}} x درجة القواعد + {{model_weight}} x (احتمال النموذج x 100). / Final score = {{rule_weight}} x rule score + {{model_weight}} x (model probability x 100).
- القائمة السوداء تعطي 100 مباشرة. / A blacklist hit forces 100.
- عند تعطل خدمة الذكاء يعتمد النظام على القواعد فقط (used_fallback). / If the AI service is down, rules alone decide (used_fallback).
- OTP: صلاحية {{otp_ttl}} ثانية و{{otp_attempts}} محاولات كحد أقصى. / OTP validity {{otp_ttl}}s, {{otp_attempts}} attempts max.

## 2. مبدأ الإنسان في الحلقة / Human in the loop

- المساعد الذكي **لا يجمّد ولا يحظر ولا يغيّر** أي شيء بنفسه. / The AI assistant never freezes, blocks or changes anything itself.
- كل ما يقترحه (ملاحظة حالة أو إجراء) يُخزَّن كاقتراح بحالة `pending` بانتظار موافقة المحلل. / Everything it suggests is stored as a `pending` proposal awaiting analyst approval.
- تجميد الحساب أو المستفيد يتطلب موافقة مشرف إضافية (four-eyes). / Freezing an account or beneficiary also needs a supervisor's four-eyes approval.
- الحالات التي يتجاوز مبلغها {{high_value_threshold}} تتطلب موافقة المشرف عند الإغلاق. / Cases above {{high_value_threshold}} need supervisor approval to close.

## 3. التعامل مع البيانات / Data handling

- جميع أرقام الحسابات والهواتف والبريد وعناوين IP والأسماء تصل مقنّعة. / Account numbers, phones, emails, IPs and names are always masked.
- لا تحاول إعادة بناء القيم المقنّعة ولا تطلبها من العميل. / Never attempt to reconstruct masked values.
- كل استدعاء لأداة أو مورد أو توجيه يُسجَّل في سجل التدقيق. / Every tool, resource and prompt call is written to the audit log.
- المحلل يرى التنبيهات المسندة إليه أو غير المسندة فقط. / Analysts only see alerts assigned to them or unassigned.

## 4. أسلوب التوصية / How to recommend

1. اذكر الدرجة والقرار الآلي والأسباب الرئيسية. / State the score, automated decision and main reasons.
2. اربط كل استنتاج بدليل (معاملة، جهاز، مستفيد). / Tie each conclusion to evidence.
3. اقترح إجراءً واحداً واضحاً عبر `propose_action` أو ملاحظة عبر `add_case_note`، واترك القرار للمحلل. / Suggest one clear action and leave the decision to the analyst.
