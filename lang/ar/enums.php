<?php

return [
    'EventType' => [
        'login' => 'تسجيل دخول',
        'transfer' => 'تحويل',
        'add_beneficiary' => 'إضافة مستفيد',
        'change_phone' => 'تغيير الهاتف',
        'raise_limit' => 'رفع الحد',
        'open_account' => 'فتح حساب',
    ],
    'AccountStatus' => [
        'active' => 'نشط',
        'frozen' => 'مجمّد',
        'closed' => 'مغلق',
    ],
    'TransactionStatus' => [
        'pending' => 'قيد المعالجة',
        'completed' => 'مكتملة',
        'held' => 'معلّقة',
        'blocked' => 'محظورة',
        'rejected' => 'مرفوضة',
    ],
    'Decision' => [
        'allow' => 'سماح',
        'challenge' => 'تحقق إضافي',
        'block' => 'حظر',
    ],
    'Priority' => [
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
        'critical' => 'حرجة',
    ],
    'AlertStatus' => [
        'open' => 'مفتوح',
        'in_review' => 'قيد المراجعة',
        'escalated' => 'مُصعَّد',
        'pending_info' => 'بانتظار معلومات',
        'closed' => 'مغلق',
    ],
    'AnalystDecision' => [
        'confirmed_fraud' => 'احتيال مؤكد',
        'false_positive' => 'إنذار كاذب',
        'escalated' => 'تصعيد',
        'info_requested' => 'طلب معلومات',
    ],
    'CaseStatus' => [
        'open' => 'مفتوحة',
        'investigating' => 'قيد التحقيق',
        'pending_approval' => 'بانتظار الاعتماد',
        'closed' => 'مغلقة',
    ],
    'OtpStatus' => [
        'pending' => 'معلّق',
        'verified' => 'تم التحقق',
        'failed' => 'فشل',
        'expired' => 'منتهي',
    ],
    'ApprovalStatus' => [
        'pending' => 'معلّق',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
    ],
    'ApprovalType' => [
        'freeze_account' => 'تجميد حساب',
        'unfreeze_account' => 'رفع تجميد حساب',
        'close_high_value_case' => 'إغلاق حالة عالية القيمة',
        'freeze_beneficiary' => 'تجميد مستفيد',
    ],
    'AccountActionType' => [
        'block_transaction' => 'حظر معاملة',
        'require_otp' => 'طلب رمز تحقق',
        'freeze_account' => 'تجميد حساب',
        'unfreeze_account' => 'رفع تجميد حساب',
        'freeze_beneficiary' => 'تجميد مستفيد',
    ],
    'AccountActionStatus' => [
        'pending_approval' => 'بانتظار الاعتماد',
        'executed' => 'منفّذ',
        'rejected' => 'مرفوض',
    ],
    'AiProposalStatus' => [
        'pending' => 'معلّق',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
    ],
    'LabelSource' => [
        'analyst' => 'محلل',
        'customer' => 'عميل',
        'investigation' => 'تحقيق',
        'bank' => 'البنك',
    ],
    'CustomerReportStatus' => [
        'new' => 'جديد',
        'reviewing' => 'قيد المراجعة',
        'resolved' => 'تمت المعالجة',
    ],
    'RuleSuggestionStatus' => [
        'pending' => 'معلّق',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
    ],
    'WatchlistType' => [
        'blacklist' => 'قائمة سوداء',
        'whitelist' => 'قائمة بيضاء',
    ],
    'WatchlistEntity' => [
        'device' => 'جهاز',
        'account' => 'حساب',
        'ip' => 'عنوان IP',
        'beneficiary' => 'مستفيد',
    ],
    'ModelStatus' => [
        'champion' => 'النموذج الرئيسي',
        'shadow' => 'ظل',
        'candidate' => 'مرشح',
        'archived' => 'مؤرشف',
    ],
];
