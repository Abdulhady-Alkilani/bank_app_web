# خطة العمل — نظام كشف العمليات المصرفية المشبوهة (قسم الويب + MCP)

> المصادر: وثيقة التوصيف v1.0، البند الإضافي (مقارنة النماذج)، مخطط قاعدة البيانات (dbdiagram)، وصورتا التعديلات وخطة المشي.
> النطاق هنا: **تطبيق Laravel 13 (الويب + API + خادم MCP)** فقط. خدمة الذكاء (Python/FastAPI) مشروع منفصل لاحقاً،
> ويُبنى لها هنا **عقد تكامل** (`AiScoringClient`) مع تنفيذ وهمي `FakeAiScoringClient` وتنفيذ HTTP جاهز `HttpAiScoringClient`.

## 0. قرارات التصميم المُغلقة (من صور التعديلات)

- حذف دور «عالم البيانات» (DS-01..DS-10). التدريب سكربتات Python خارج النظام.
- `ml_models` و`model_runs` تبقيان سجلَّ إصدارات يربط كل درجة بالنموذج الذي أنتجها (للتدقيق والتفسير)، عرضها للمدير والمشرف للقراءة، مع ترقية Champion وتسجيلها في التدقيق.
- ADM-02: لا صلاحية «تدريب»؛ تضاف «عرض النماذج وإصداراتها» للمدير. SYS-09: «تنبيه المدير» بدل عالم البيانات.
- API: لا `POST /models/train`؛ `GET /models/compare` للمدير والمشرف. أداة MCP `get_model_comparison` اختيارية (نوفرها).
- `labels.alert_id` و`labels.event_id` nullable (سيناريو الاستيلاء يتضمن أحداثاً غير مالية).
- المبدأ: الويب يكتمل بعميل ذكاء وهمي، ثم يُستبدل بالخدمة الحقيقية دون تغيير الباقي.
- الصلاحيات: جداول مخصصة حسب المخطط (`roles`, `permissions`, `role_user`, `permission_role`) بدل جداول Spatie.
- الزمن الحقيقي: Laravel Reverb غير متوافق حالياً مع تبعيات المشروع (guzzlehttp/psr7 v3) → إشعارات قاعدة البيانات + Livewire polling (قابل للاستبدال بـ Reverb لاحقاً).
- PDF: `mpdf/mpdf` (يدعم العربية RTL). Excel: `openspout/openspout`.

## 1. البيئة والحزم (مُثبتة مسبقاً — لا تُضاف حزم جديدة)

PHP 8.5 (Herd, Windows) · Laravel 13.39 · MySQL `bank_db` · الاختبارات Pest على sqlite `:memory:` ·
livewire/livewire 4 · laravel/fortify (2FA) · laravel/sanctum · laravel/mcp 1.1 · laravel/boost (dev) · mpdf · openspout ·
npm: tailwindcss 4, apexcharts, cytoscape. Redis اختياري (الخصائص اللحظية عبر `Cache` بمخزن قابل للضبط).

## 2. الأدوار والصلاحيات

| الدور (slug) | الوصف |
|---|---|
| `admin` | المستخدمون، الأدوار، الإعدادات، القواعد، القوائم، الصحة، التدقيق، مفاتيح التكامل ورموز MCP، النسخ الاحتياطي، سجل النماذج |
| `supervisor` | التوزيع، اعتماد four-eyes، المؤشرات، مراجعة الجودة، اقتراحات القواعد، تقارير الامتثال، التدقيق (قراءة) |
| `analyst` | طابور التنبيهات، التفاصيل، ملف العميل، الشبكة، القرار، الإجراءات، الحالات، البحث، المساعد، PDF، اقتراح قاعدة |
| `customer` | البوابة التجريبية |

الصلاحيات (name): `alerts.view, alerts.decide, cases.manage, accounts.freeze, accounts.unfreeze, approvals.approve, users.manage, roles.manage, settings.manage, rules.manage, lists.manage, audit.view, audit.export, integrations.manage, models.view, models.promote, reports.view, assignments.manage, qa.review, rule_suggestions.create, rule_suggestions.review, system.health, backups.manage, mcp.use, customer.portal`.
التحقق: `User::hasRole()`, `User::hasPermission()`, و`Gate::before` يربط أي ability باسم صلاحية. Middleware: `role:admin,supervisor` و`permission:alerts.view`.

## 3. قاموس الحالات (Enums في `app/Enums`)

- `EventType`: login, transfer, add_beneficiary, change_phone, raise_limit, open_account
- `AccountStatus`: active, frozen, closed · `TransactionStatus`: pending, completed, held, blocked, rejected
- `Decision`: allow, challenge, block · `Priority`: low, medium, high, critical
- `AlertStatus`: open, in_review, escalated, pending_info, closed · `AnalystDecision`: confirmed_fraud, false_positive, escalated, info_requested
- `CaseStatus`: open, investigating, pending_approval, closed · `OtpStatus`: pending, verified, failed, expired
- `ApprovalStatus`: pending, approved, rejected · `ApprovalType`: freeze_account, unfreeze_account, close_high_value_case, freeze_beneficiary
- `AccountActionType`: block_transaction, require_otp, freeze_account, unfreeze_account, freeze_beneficiary · `AccountActionStatus`: pending_approval, executed, rejected
- `AiProposalStatus`: pending, approved, rejected · `LabelSource`: analyst, customer, investigation, bank
- `CustomerReportStatus`: new, reviewing, resolved · `RuleSuggestionStatus`: pending, accepted, rejected
- `WatchlistType`: blacklist, whitelist · `WatchlistEntity`: device, account, ip, beneficiary · `ModelStatus`: champion, shadow, candidate, archived

## 4. قاعدة البيانات

كل جداول المخطط كما هي (users…notifications) + جداول Laravel/Fortify/Sanctum. مفاتيح أجنبية وفهارس على:
`transactions(account_id, transaction_time)`, `account_events(account_id, event_time)`, `idempotency_key` (unique)،
`devices.device_fingerprint` (unique)، `accounts.account_number` (unique)، `risk_scores(score)`, `alerts(status, priority)`.
`api_clients.secret` يُخزن مشفراً (cast `encrypted`) لأن HMAC يحتاج السر الأصلي.
الإعدادات الافتراضية (`settings`): `decision.allow_max=39`, `decision.challenge_max=74`, `scoring.rule_weight=0.4`, `scoring.model_weight=0.6`,
`otp.ttl_seconds=300`, `otp.max_attempts=3`, `transfer.default_limit=20000`, `cases.high_value_threshold=50000`, `qa.sample_rate=0.1`, `alerts.analyst_daily_capacity=50`.

## 5. مسار التقييم (SYS-01..10, EXT-01..06)

```
Bank/Simulator → API (HMAC + Idempotency + RateLimit) → EventIngestor (حفظ transaction أو account_event)
 → FeatureService (SQL + Cache counters) → RulesEngine (قواعد JSON + القوائم)
 → AiScoringClient (Fake | Http, timeout 250ms, Fallback=rules only)
 → DecisionEngine (score = rule_w*rule_score + model_w*prob*100 ؛ blacklist ⇒ 100)
 → risk_scores (score, decision, reasons, features, latency_ms, model_run_id, used_fallback)
 → DecisionExecutor: allow | challenge ⇒ OtpService (Mail→log/Mailpit) + Alert | block ⇒ تجميد + Alert + BankWebhook
 → [Queue] AlertService: أولوية + إسناد تلقائي (أقل عبء) + إشعار المحللين للحالات العالية
```

عقد قواعد JSON: `{"event_types":["transfer"],"all":[{"feature":"amount_to_avg_ratio","op":">=","value":10},{"feature":"minutes_since_phone_change","op":"<=","value":60}]}` (يدعم `any` أيضاً، والمعاملات `> >= < <= == != in`).
الخصائص: `amount, amount_to_avg_ratio, is_new_device, device_account_count, logins_new_device_1h, minutes_since_phone_change, minutes_since_beneficiary_added, beneficiary_is_new, beneficiary_account_age_days, beneficiary_fan_out_1h, txn_count_1h, location_changed, hour_of_day, limit_raised_recently`.

## 6. عقد خدمة الذكاء (للمشروع اللاحق)

`App\Contracts\AiScoringClient`: `score(array $features, array $context): AiScoreResult` و`health(): bool`.
`config/fraud.php` → `ai.driver = fake|http`, `ai.url`, `ai.token`, `ai.timeout_ms`.
Python (لاحقاً): `POST {url}/v1/score` ← `{features, context}` → `{probability, model_name, model_version, top_features:[{name,value,impact}], latency_ms}` · `GET {url}/health`.
تسجيل نتائج التدريب: `POST /api/v1/ml/model-runs` (Bearer رمز خدمة) يُنشئ `ml_models`/`model_runs` بالمقاييس.

## 7. API (`/api/v1`)

خارجي (HMAC: `X-Api-Key`, `X-Timestamp`, `X-Signature = hmac_sha256(secret, timestamp + "." + body)`, `Idempotency-Key`):
`POST /events` (غير متزامن 202) · `POST /score` (متزامن، هدف <300ms) · `POST /otp/verify` · `POST /verification-results` (EXT-04 → وسوم) · `POST /sync` (EXT-05 دفعي).
موظفون (Sanctum): `GET /alerts` · `GET /alerts/{id}` · `POST /alerts/{id}/decision` · `GET /accounts/{id}/network` · `GET /models/compare`.
خدمة ML: `POST /ml/model-runs`. توثيق `public/docs/openapi.yaml`.

## 8. مراحل التنفيذ وتوزيع الوكلاء (Sonnet · medium)

| الموجة | الوكيل | المراحل | الملكية (لا يعدّل غيرها إلا للضرورة) |
|---|---|---|---|
| 1 | **A — Foundation** | 1، 2 (جزئياً) | migrations, Enums, Models+Factories, Seeders, RBAC, Layout RTL/i18n, Auth+2FA, خدمات النواة المشتركة |
| 2 | **B — Scoring & External API** | 3، 4، 5 | `app/Services/Scoring`, `Ingestion`, `Otp`, `Alerts`, `AI client`, API خارجي, Jobs |
| 2 | **C — Analyst Workspace** | 6 | `app/Livewire/Analyst`, Case/Investigation services, staff API, PDF, notifications |
| 2 | **D — Admin & Supervisor** | 2، 7 | `app/Livewire/Admin`, `app/Livewire/Supervisor`, Reports, Backup, ML registry |
| 3 | **E — Customer Portal & Simulator** | 8 | `app/Livewire/Customer`, `bank:simulate` + سيناريو أحمد |
| 3 | **F — MCP Server** | 9 | `app/Mcp`, `routes/ai.php`, Masking, proposals |
| 3 | **G — DevOps & Docs** | 11 (جزئياً) | Docker Compose, Nginx, GitHub Actions, OpenAPI |
| 4 | **H — QA** | 11 | تشغيل الكل، إصلاح، تغطية، build |

خدمات النواة (Foundation) المشتركة: `AuditLogger`, `SettingsService`, `Masker`, `GraphService` (عمق ≤3)، `ApprovalService` (four-eyes)،
`AccountActionService` (تنفيذ/طلب تجميد ورفعه)، `LabelService`، و`ScoringPipeline` كواجهة (يُنفذها B).

ملفات المسارات منفصلة لكل وكيل: `routes/web/{admin,supervisor,analyst,customer}.php`، `routes/api/v1/{external,staff,ml}.php`، `routes/ai.php`.
ملفات الترجمة: `lang/{ar,en}/{common,admin,supervisor,analyst,customer}.php`.

## 9. مصفوفة التتبع (ملخص)

ADM-01..09 → D · AN-01 → A · AN-02..13 → C · SUP-01..07 → D · CUS-01..07 → E · EXT-01..06 → B · AI-01..06 → F · SYS-01..08,10 → B · SYS-09 → D (تنبيه المدير بالانحراف عند ورود model_run) · DS → محذوف.

## 10. معايير القبول

`php artisan migrate:fresh --seed` ينجح على MySQL · `php artisan test` أخضر · `npm run build` ينجح ·
`php artisan bank:simulate --scenario=ahmed` يرفع الدرجة تدريجياً حتى الإيقاف ويُنشئ تنبيهات/حالة ·
`php artisan mcp:inspector` يعرض الأدوات · واجهة عربية RTL مع خيار الإنكليزية.
