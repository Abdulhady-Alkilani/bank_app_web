# خطة عمل طرفية الذكاء الاصطناعي (خدمة Python) — نظام كشف العمليات المصرفية المشبوهة

> **المصادر:** وثيقة التوصيف v1.0 (بنود DS-01..10 وSYS-02/05/06/09/10 والمتطلبات غير الوظيفية)، وملحق مقارنة النماذج (`plan/بند.pdf`)، وصور التعديلات (حذف دور عالم البيانات)، وما بُني فعلاً في تطبيق Laravel (`plan/WORK_PLAN.md` و`plan/AI_SERVICE_CONTRACT.md` والكود).
>
> **النطاق:** مشروع Python مستقل (مجلد خارج هذا المستودع، مقترح `../fraud-ai-service`) يضم خدمة تقييم لحظية (FastAPI) وسكربتات تدريب وتقييم ومراقبة. لا واجهة ويب له، والعرض كله في Laravel.

---

## 1. ملخص تنفيذي

تطبيق Laravel يعمل الآن كاملاً بعميل ذكاء وهمي (`AI_DRIVER=fake`). المطلوب من مشروع الذكاء ثلاثة أشياء فقط:

1. **خدمة تقييم** `POST /v1/score` و`GET /health` بالعقد الموجود حرفياً، بزمن p95 أقل من 100ms.
2. **خط تدريب** يقارن النماذج حسب منهجية الملحق، ويسجل كل تشغيل في Laravel عبر `POST /api/v1/ml/model-runs`.
3. **مراقبة انحراف** دورية ترسل `drift_detected` فيصل التنبيه لمدراء النظام (SYS-09).

عند تشغيل الخدمة يكفي في `.env` لـ Laravel:

```env
AI_DRIVER=http
AI_SERVICE_URL=http://127.0.0.1:8001
AI_SERVICE_TOKEN=<سر مشترك>
ML_INGEST_TOKEN=<سر آخر لسكربتات التدريب>
```

لا يلزم أي تعديل آخر في Laravel للتشغيل الأساسي. التحسينات المقترحة على جانب Laravel في §10.

---

## 2. ما هو جاهز في Laravel (نقاط التكامل)

| العنصر | الموقع | الوظيفة |
|---|---|---|
| واجهة العميل | `app/Contracts/AiScoringClient.php` | `score(features, context)` و`health()` |
| عميل HTTP | `app/Services/Ai/HttpAiScoringClient.php` | Bearer token، مهلة `AI_TIMEOUT_MS` (250ms) للاتصال والقراءة، قص الاحتمال إلى [0,1] |
| العميل الوهمي | `app/Services/Ai/FakeAiScoringClient.php` | انحدار لوجستي بأوزان يدوية: **مرجع أداء أدنى (baseline)** يجب أن يتفوق عليه النموذج الحقيقي |
| تعطيل الذكاء | `AI_DRIVER=none` أو إعداد `ai.enabled=false` | قواعد فقط |
| الرجوع للقواعد (SYS-10) | `DefaultScoringPipeline::callAi` | أي استثناء أو مهلة أو رد غير صالح ⇒ `used_fallback=true` |
| حساب الخصائص | `app/Services/Scoring/FeatureService.php` | 17 خاصية (انظر §4) تُحفظ مع كل درجة في `risk_scores.features` |
| دمج الدرجة | `app/Services/Scoring/DecisionEngine.php` | `score = (0.4·rule + 0.6·p·100)/(0.4+0.6)`، القائمة السوداء أو الحساب المجمّد ⇒ 100، القائمة البيضاء ⇒ ×0.5 |
| العتبات | إعدادات المدير | سماح ≤39، تحقق OTP ≤74، إيقاف ≥75 |
| التفسير (SYS-06) | `DecisionEngine` | كل عنصر من `top_features` يصبح سبباً `model:<feature>` بنص عربي وإنكليزي من `lang/*/scoring.php` |
| سجل النماذج | جداول `ml_models` و`model_runs`، صفحة المدير «سجل النماذج» | حالات `champion / shadow / candidate / archived`، ترقية يدوية مدققة |
| استقبال نتائج التدريب | `POST /api/v1/ml/model-runs` (`MlModelRunController`) | Bearer `ML_INGEST_TOKEN`، نموذج جديد ⇒ `candidate`، `drift_detected=true` ⇒ إشعار المدراء |
| مقارنة النماذج | `app/Support/ModelScore.php`، `GET /api/v1/models/compare`، أداة MCP `get_model_comparison` | الأوزان في §7 |
| الوسوم (Labels) | جدول `labels` | من قرار المحلل، ونتيجة البنك `POST /verification-results`، وتأكيد أو إنكار العميل |
| صحة النظام | `SystemHealthService` | يستدعي `health()` ويعرض up/down |
| Docker | `docker-compose.yml` | خدمة `ai-service` معلقة جاهزة (منفذ 8001، مجلد `/models`، healthcheck) |
| بيانات اصطناعية | `php artisan bank:simulate --scenario=ahmed\|normal\|mule\|shared-device` | تولد أحداثاً بخصائص ووسوم حقيقية من مسار التقييم نفسه |

---

## 3. المتطلبات المغطاة

### من وثيقة التوصيف (بعد حذف دور عالم البيانات تُنفذ كسكربتات)

| البند | المحتوى | التنفيذ في مشروع Python |
|---|---|---|
| DS-01 | تحميل البيانات (CSV، IEEE-CIS، PaySim) | `data/loaders/` لكل مصدر + قارئ قاعدة Laravel (قراءة فقط) |
| DS-02 | هندسة الخصائص | `features/` بنفس تعريفات `FeatureService` حرفياً (§4) |
| DS-03 | تدريب نماذج متعددة | `training/models/` لكل خوارزمية في §6 |
| DS-04 | ضبط Hyperparameters | Optuna مع Rolling Validation |
| DS-05 | المقاييس: PR-AUC وROC-AUC وRecall وPrecision وPrecision@K وF1 | `evaluation/metrics.py` يرسل المفاتيح المعتمدة في §7 |
| DS-06 | ترقية Champion والتشغيل الخفي (Shadow) | الترقية تبقى في Laravel (يدوية ومدققة)، والتشغيل الخفي في §10 |
| DS-07 | تفسير SHAP | `top_features` في كل استجابة + تقرير SHAP عام لكل تشغيل |
| DS-08 | مراقبة الانحراف | `monitoring/drift.py` (PSI) ⇒ `drift_detected` |
| DS-09 | سجل التجارب | `model_runs` في Laravel + MLflow محلي اختياري |
| DS-10 | تصدير النموذج | joblib للنماذج الشجرية، ONNX للشبكات |

### متطلبات النظام وغير الوظيفية

| البند | المطلوب | الأثر على الخدمة |
|---|---|---|
| SYS-02 | خصائص لحظية | الخدمة **لا تحسب** الخصائص، بل تستقبلها جاهزة من Laravel |
| SYS-05 | دمج القواعد والنموذج | يتم في Laravel، الخدمة تعيد الاحتمال فقط |
| SYS-06 | سبب مفهوم لكل قرار | `top_features` بأسماء الخصائص كما هي (لها ترجمة جاهزة) |
| SYS-09 | تنبيه عند الانحراف | عبر `drift_detected` |
| SYS-10 | الرجوع عند التعطل | الخدمة لا تملك أن توقف القرار، والمهلة 250ms |
| الأداء | الرحلة كاملة p95 أقل من 300ms عند 100 طلب/ثانية | زمن الاستدلال p95 أقل من 100ms، وكل نموذج p95 فوق 300ms يُستبعد من المسار اللحظي |
| الدقة | Recall لا يقل عن 85% مع Precision لا تقل عن 80% تقريباً، وPR-AUC مرجعي | معيار القبول للنموذج البطل (§11) |
| التوفر | 99.5% | healthcheck وإعادة تشغيل تلقائي، والرجوع للقواعد يغطي الانقطاع |
| الأمان والخصوصية (PDPL) | لا بيانات شخصية في النموذج | الخصائص رقمية فقط، و`context` لا يُستخدم مدخلاً للنموذج ولا يُسجل كاملاً |

---

## 4. عقد الخصائص (مصدر الحقيقة: `FeatureService.php`)

يجب أن يحسب خط التدريب الخصائص **بالتعريف نفسه** المستخدم في الإنتاج. الأفضل التدريب مباشرة على `risk_scores.features` (القيم التي رآها النظام لحظة القرار)، فلا يحدث انحراف بين التدريب والتشغيل.

| الخاصية | النوع | قد تكون null | المعنى |
|---|---|---|---|
| `amount` | float | لا | مبلغ العملية (0 للأحداث غير المالية) |
| `amount_to_avg_ratio` | float | لا | المبلغ ÷ متوسط رصيد العميل |
| `is_new_device` | 0/1 | لا | أول ظهور للجهاز مع هذا العميل |
| `device_account_count` | int | لا | عدد الحسابات المرتبطة بالجهاز |
| `logins_new_device_1h` | int | لا | دخولات من أجهزة جديدة خلال ساعة |
| `minutes_since_phone_change` | number | نعم | دقائق منذ تغيير الجوال |
| `minutes_since_beneficiary_added` | number | نعم | دقائق منذ إضافة المستفيد |
| `beneficiary_is_new` | 0/1 | لا | مستفيد أُضيف خلال 24 ساعة |
| `beneficiary_account_age_days` | number | نعم | عمر حساب المستفيد |
| `beneficiary_fan_out_1h` | int | لا | عدد المرسلين المختلفين لنفس المستفيد خلال ساعة (حسابات وسيطة) |
| `txn_count_1h` | int | لا | عدد العمليات خلال ساعة |
| `location_changed` | 0/1 | لا | مدينة أو IP مختلف عن النمط |
| `hour_of_day` | int | لا | الساعة 0..23 |
| `limit_raised_recently` | 0/1 | لا | رفع حد التحويل مؤخراً |
| `blacklist_hit` / `whitelist_hit` / `account_frozen` | 0/1 | لا | **تُستبعد من مدخلات النموذج** لأن Laravel يعالجها بقواعد صلبة (تسرب معلومات) |

قواعد:
- `null` تعني «غير موجود»: تُعوض بقيمة حارسة (مثل `-1`) ومعها علم `is_missing_*`، بالطريقة نفسها في التدريب والتشغيل.
- الخدمة **تتجاهل أي مفتاح غير معروف** (قد تضيف Laravel خصائص لاحقاً)، وتحتفظ بقائمة خصائص مُرقّمة الإصدار `feature_set_version` مع كل نموذج.
- عند غياب خاصية يحتاجها النموذج تُعامل كـ `null` ولا يُرفض الطلب.

---

## 5. البيانات

### 5.1 المصادر

| المصدر | الاستخدام | ملاحظات |
|---|---|---|
| **PaySim** (Kaggle) | تدريب أولي وتجارب | محاكي تحويلات mobile money بوسم `isFraud`، ويُربط بخصائصنا (المبلغ، النسبة، المستفيد الجديد، fan-out) |
| **IEEE-CIS** (Kaggle) | تجارب الأجهزة والهوية | أعمدة `DeviceInfo` و`id_*` تقرّب `is_new_device` و`device_account_count` |
| **بيانات Laravel الاصطناعية** | التدريب الأساسي والتقييم النهائي | `bank:simulate` بسيناريوهات normal وahmed وmule وshared-device بكميات كبيرة، ثم `risk_scores.features` مع `labels` |
| **وسوم الإنتاج** (مستقبلاً) | إعادة التدريب الدوري | جدول `labels` (محلل، بنك، عميل) |

> الملحق يشترط إثبات التفسير والأداء على PaySim وIEEE-CIS. لذلك تُدرَّب المقارنة على المصدرين العامين، ويُدرَّب البطل المنشور على خصائص Laravel.

### 5.2 استخراج بيانات التدريب من Laravel

استعلام قراءة فقط (مستخدم MySQL بصلاحية SELECT على الجداول المطلوبة):

```sql
SELECT rs.id, rs.evaluated_at, rs.features, rs.rule_score, rs.decision,
       COALESCE(l_tx.is_fraud, l_ev.is_fraud) AS is_fraud,
       t.account_id, t.beneficiary_id, ae.event_type
FROM risk_scores rs
LEFT JOIN transactions t   ON t.id = rs.transaction_id
LEFT JOIN account_events ae ON ae.id = rs.event_id
LEFT JOIN labels l_tx ON l_tx.transaction_id = rs.transaction_id
LEFT JOIN labels l_ev ON l_ev.event_id = rs.event_id
ORDER BY rs.evaluated_at;
```

- العمليات بلا وسم لا تُعامل كسليمة تلقائياً إلا بعد مدة نضج (مثلاً 30 يوماً بلا بلاغ)، وإلا تُستبعد.
- عند تعدد الوسوم يُعتمد ترتيب الثقة: `bank` ثم `investigation` ثم `analyst` ثم `customer`.
- يمكن إضافة أمر تصدير في Laravel بدل الاتصال المباشر بالقاعدة (§10، اختياري).

### 5.3 التقسيم

تقسيم زمني (Temporal Split) حسب `evaluated_at`: 70% تدريب، 15% تحقق، 15% اختبار. **ممنوع التقسيم العشوائي** لأنه يسرّب المستقبل.

---

## 6. النماذج المرشحة ومراحل المقارنة (من الملحق)

| المرحلة | النماذج | الهدف |
|---|---|---|
| 1 — الأساس | Logistic Regression (Baseline)، Random Forest | مرجع قابل للتفسير، والتفوق على `FakeAiScoringClient` |
| 2 — Boosting | XGBoost، LightGBM، CatBoost | المرشح الأقوى للبيانات الجدولية، وSHAP سريع عبر TreeExplainer |
| 3 — كشف الشذوذ | Isolation Forest، Autoencoder | أنماط احتيال جديدة بلا وسوم (درجة مساعدة) |
| 4 — الشبكات العصبية | MLP | مقارنة فقط |
| 5 — التسلسل | GRU / LSTM / Transformer على تسلسل أحداث الحساب | نمط الاستيلاء (دخول ← تغيير جوال ← رفع حد ← تحويل) |
| 6 — الرسم البياني | GraphSAGE / GAT / R-GCN (GNN) على شبكة الحساب والجهاز وIP والمستفيد | الحسابات الوسيطة (Mule) والأجهزة المشتركة. GraphSAGE لأنه استقرائي (Inductive) ويعمل على عقد جديدة |
| 7 — التجميع | Stacking: GraphSAGE embeddings + GRU + LightGBM | توصية الملحق النهائية |

**توصية الملحق:** يُبنى البطل على Gradient Boosting (LightGBM أو XGBoost) لأنه الأسرع والأوضح تفسيراً، ثم يُضاف GraphSAGE وGRU كمولدات خصائص (embeddings) تُغذّي LightGBM. Transformers تُستبعد من المسار اللحظي إن تجاوزت حدود الزمن، ويُستخدم MLP للمقارنة فقط.

**قيد المسار اللحظي:** embeddings الرسم البياني والتسلسل **تُحسب مسبقاً دورياً** (batch) وتُخزن في مخزن الخصائص للخدمة (Redis أو ملف parquet مفهرس برقم الحساب)، ولا تُحسب داخل طلب `/v1/score`. يمرر Laravel `account_id` في `context`، وتُستخدم الـ embeddings في الخدمة مفتاحاً للبحث لا مدخلاً من Laravel.

---

## 7. منهجية التدريب والتقييم (خطوات الملحق العشر)

1. **البيانات:** المصادر في §5، مع توثيق نسبة الاحتيال (أقل من 1%).
2. **التقسيم الزمني** 70/15/15.
3. **عدم التوازن:** أوزان الأصناف (`scale_pos_weight` / `class_weight`) أو Focal Loss للشبكات، وSMOTE على التدريب فقط (لا على التحقق أو الاختبار).
4. **الضبط:** Optuna (50–100 محاولة) مع Rolling Validation على نوافذ زمنية متتالية، وهدف التحسين PR-AUC.
5. **الثبات:** تكرار التدريب بـ 3 إلى 5 بذور (seeds) وحساب `pr_auc_std`.
6. **المعايرة:** Platt (Sigmoid) أو Isotonic على مجموعة التحقق، لأن Laravel يضرب الاحتمال ×100 ويدمجه بالقواعد، فلا بد أن يكون الاحتمال معايراً.
7. **التسجيل:** كل تشغيل يُرسل إلى `model_runs` بالمقاييس.
8. **العتبة:** تقرير Precision/Recall عند عتبات Laravel الفعلية بعد الدمج (40 و75) على مجموعة الاختبار.
9. **التفسير:** SHAP على PaySim وIEEE-CIS وبيانات Laravel، وأهم 5 خصائص لكل قرار.
10. **النشر:** Champion للنموذج الفعلي وShadow للمرشح، وترقية يدوية من المدير بعد التحقق.

### المقاييس ومفاتيحها (يقرؤها `app/Support/ModelScore.php`)

| المفتاح | الوزن | المقياس |
|---|---|---|
| `pr_auc` | 30% | 0–1 |
| `recall_at_p90` (Recall عند Precision 90%) | 20% | 0–1 |
| `precision_at_k` (K = سعة المحللين اليومية، الافتراضي 50) | 15% | 0–1 |
| `interpretability` | 10% | 0–1 حسب سلّم (§7.1) |
| `p95_latency_ms` | 10% | ميلي ثانية، الدرجة `1 - latency/300`، وفوق 300 يُستبعد من المسار اللحظي |
| `stability` (أو `pr_auc_std` فتُحسب `1 - std`) | 10% | 0–1 |
| `resource_score` | 5% | 0–1 (الأعلى أرخص) |
| `f1`، `f2`، `roc_auc` | 0% | للعرض فقط |
| `drift_detected` | — | `true` يرسل إشعاراً للمدراء |

مقاييس إضافية تُخزن كما هي وتفيد العرض: `feature_set_version`، `dataset`، `n_train`، `fraud_rate`، `calibration`، `psi_max`. **الدقة (Accuracy) لا تُستخدم** بسبب عدم التوازن.

> تنبيه: أوزان المقارنة في Laravel مأخوذة من فهم جزئي لجدول الملحق. يجب مراجعتها مقابل الملحق الأصلي قبل اعتماد أول مقارنة رسمية.

### 7.1 سلّم قابلية التفسير (مقترح)

LR = 1.0 · نماذج شجرية مع SHAP = 0.8 · Random Forest = 0.7 · MLP/GRU مع SHAP تقريبي = 0.4 · GNN = 0.3 · Stacking = حسب المكوّن الأضعف.

---

## 8. هيكل مشروع Python المقترح

```
fraud-ai-service/
├── pyproject.toml            # Python 3.11، uv أو poetry
├── Dockerfile                # python:3.11-slim، uvicorn، المنفذ 8001
├── .env.example              # AI_SERVICE_TOKEN, MODEL_DIR, LARAVEL_URL, ML_INGEST_TOKEN, DB_DSN_READONLY
├── app/                      # خدمة التقييم (FastAPI)
│   ├── main.py               # /v1/score, /health, /metrics
│   ├── auth.py               # Bearer + hmac.compare_digest
│   ├── schemas.py            # Pydantic: ScoreRequest/ScoreResponse
│   ├── model_store.py        # تحميل البطل (joblib/onnx) + feature_set + calibrator + explainer
│   ├── explain.py            # SHAP TreeExplainer → top_features
│   └── embeddings.py         # (المرحلة 6) قراءة embeddings المحسوبة مسبقاً
├── features/
│   ├── spec.py               # قائمة الخصائص المرقمة + التعويض عن null (مطابقة لـ FeatureService)
│   └── mappers/{paysim,ieee_cis}.py
├── data/loaders/             # paysim.py, ieee_cis.py, laravel_db.py
├── training/
│   ├── train.py              # CLI: --model lightgbm --dataset laravel --trials 100 --seeds 5
│   ├── models/               # lr.py, rf.py, xgb.py, lgbm.py, catboost.py, iforest.py, mlp.py, gru.py, graphsage.py, stacking.py
│   ├── tuning.py             # Optuna + rolling validation
│   ├── calibrate.py
│   └── export.py             # joblib / ONNX + manifest.json
├── evaluation/
│   ├── metrics.py            # pr_auc, recall_at_p90, precision_at_k, ...
│   ├── latency.py            # قياس p95 للاستدلال
│   └── report.py             # تقرير مقارنة HTML/Markdown
├── monitoring/drift.py       # PSI يومي على risk_scores.features
├── registry/laravel.py       # POST /api/v1/ml/model-runs
└── tests/                    # pytest: العقد، الزمن، التعويض، المقاييس
```

`manifest.json` لكل نموذج منشور: `model_name`، `model_version`، `algorithm`، `feature_set_version`، `features` (بالترتيب)، `calibration`، `threshold_report`، `trained_at`، `sha256` للملف.

---

## 9. خدمة التقييم (FastAPI)

### العقد (لا يتغير، مفصل في `plan/AI_SERVICE_CONTRACT.md`)

- `POST /v1/score` ← `{features, context}` ⇒ `{probability, model_name, model_version, top_features[{name,value,impact}], latency_ms}`
- `GET /health` ⇒ 200 عند تحميل النموذج، و503 قبل ذلك.
- `GET /metrics` (اختياري) لـ Prometheus: عدد الطلبات، الزمن، توزيع الاحتمالات.

### متطلبات التنفيذ

| البند | القرار |
|---|---|
| المصادقة | `Authorization: Bearer` مقارنة بـ `hmac.compare_digest`، و401 عند الخطأ (Laravel يرجع للقواعد) |
| الزمن | النموذج والمفسر يُحملان مرة عند الإقلاع، والاستدلال لطلب واحد بلا I/O، والهدف p95 أقل من 100ms |
| SHAP | `TreeExplainer` للنماذج الشجرية. للنماذج التي يبطئها SHAP تُستخدم مساهمات الخصائص المدمجة (`pred_contrib` في LightGBM) |
| `top_features` | حتى 5 عناصر، `impact` بإشارة (موجب = نحو الاحتيال)، وأسماء الخصائص كما في §4 حرفياً (لها ترجمة في `lang/*/scoring.php`) |
| الاحتمال | بعد المعايرة، في [0,1] |
| `model_name` / `model_version` | مطابقة لما سُجل في `model_runs` (`model_name` و`version_number`) |
| مدخلات غير صالحة | 422 من Pydantic (Laravel يرجع للقواعد). المفاتيح الزائدة تُتجاهل |
| السجلات | لا تُسجل الخصائص كاملة مع `context` معاً. يُكتفى بـ `account_id` والاحتمال والزمن |
| التزامن | `uvicorn` بعدد workers = عدد الأنوية، و`predict` متزامن خفيف |
| تحديث النموذج | إعادة تحميل عند تغير `MODEL_DIR/current` (symlink) أو `POST /admin/reload` بتوكن منفصل |
| Shadow (اختياري) | تحميل نموذج ثانٍ وتسجيل احتماله في سجل منفصل دون إعادته |

### اختبارات الخدمة

- عقد الاستجابة (schema) مطابق للمثال في `AI_SERVICE_CONTRACT.md`.
- قيم `null` ومفاتيح زائدة ومفاتيح ناقصة لا تُسقط الخدمة.
- اختبار زمن: 1000 طلب، p95 أقل من 100ms محلياً.
- اختبار تكامل مع Laravel: `AI_DRIVER=http` ثم `php artisan bank:simulate --scenario=ahmed --reset` يجب أن ينتهي بالإيقاف، و`used_fallback=false` في `risk_scores`.

---

## 10. تعديلات مقترحة على جانب Laravel

ليست مطلوبة للتشغيل الأساسي، لكنها تكمل التتبع والتشغيل الخفي:

| # | التعديل | السبب | الحجم |
|---|---|---|---|
| L1 | ✅ **تم**: `risk_scores.model_run_id` يُربط بتشغيل النموذج الذي أجاب فعلاً (`model_name` + `model_version`) عبر `ModelRegistry::runFor` | عند الرجوع للقواعد أو إصدار غير مسجل تبقى القيمة فارغة، ويُسجل تحذير في السجل للإصدار غير المسجل | — |
| L2 | ✅ **تم**: عرض `model_name`/`model_version` (أو «القواعد فقط») في تفاصيل التنبيه وأداة MCP `explain_score`، مع احتمال النموذج الظلي عند وجوده | الشفافية للمحلل | — |
| L3 | ✅ **تم**: `php artisan ml:export-training-data --from= --to= --format=csv --output=` (CSV فقط؛ parquet غير متاح في PHP) صف لكل `risk_scores` مع كل مفاتيح `features` وتسمية `is_fraud` بأولوية bank > investigation > analyst > customer | يغني عن صلاحية القاعدة المباشرة لمشروع Python | — |
| L4 | ✅ **تم**: `fraud.ai.shadow_enabled` (`AI_SHADOW_ENABLED`) يرسل مهمة Queue تستدعي `POST /v1/shadow/score` (مهلة 2 ثانية) وتخزن `shadow_probability` و`shadow_model_run_id` في `risk_scores`؛ لا تؤثر على القرار والأخطاء/404 تُتجاهل | تنفيذ DS-06 بمقارنة فعلية على حركة حقيقية دون تأثير على القرار | — |
| L5 | ✅ **تم**: عند الترقية في لوحة المدير (وبشرط `AI_DRIVER=http`) تُرسل مهمة Queue `POST /admin/reload` بـ Bearer `AI_ADMIN_TOKEN` وجسم `{model_name, model_version}`؛ الفشل يُسجل في السجل والتدقيق دون إلغاء الترقية | الترقية في Laravel تبقى يدوية ومدققة، والخدمة تتبعها | — |
| L6 | ✅ **تم**: قسم «سجل الانحراف» في صفحة سجل النماذج (التشغيلات ذات `drift_detected` مع `psi_max` والتاريخ) | متابعة SYS-09 | — |
| L7 | ✅ **تم**: خدمة `ai-service` في `docker-compose.yml` ضمن profile `ai` (`docker compose --profile ai up -d --build`، سياق البناء `AI_SERVICE_BUILD_CONTEXT`)، ومهمة CI اختيارية `ai-integration` لا تعمل إلا عند ضبط متغير المستودع `AI_SERVICE_REPO` | النشر | — |
| L8 | ⏳ **قيد المراجعة**: الأوزان في ثابت واحد `ModelScore::WEIGHTS` مع تعليق يوضح أنها بانتظار المراجعة مقابل جدول الملحق الأصلي (لم تتغير القيم) | الأوزان الحالية تفسير جزئي | صغير |

---

## 11. المراحل والتسليمات ومعايير القبول

| المرحلة | المحتوى | التسليم | معيار القبول |
|---|---|---|---|
| **P0 — الهيكل** (يومان) | المستودع، pyproject، Dockerfile، CI (ruff + pytest)، خدمة تعيد احتمالاً ثابتاً بالعقد الكامل | `GET /health` و`POST /v1/score` وهميان | Laravel بـ `AI_DRIVER=http` يعمل و`used_fallback=false` |
| **P1 — البيانات والخصائص** (4 أيام) | loaders لـ PaySim وIEEE-CIS وقاعدة Laravel، و`features/spec.py`، وتوليد 50–100 ألف حدث اصطناعي بالمحاكي | parquet مرقّم + تقرير بيانات | تطابق الخصائص مع `FeatureService` باختبار على 100 عينة من `risk_scores` |
| **P2 — الأساس** (3 أيام) | LR وRandom Forest، والمقاييس، والتقسيم الزمني، والتسجيل في Laravel | أول `model_runs` في صفحة المدير | LR يتفوق على `FakeAiScoringClient` في PR-AUC |
| **P3 — Boosting والبطل الأول** (5 أيام) | XGBoost وLightGBM وCatBoost، وOptuna، و3–5 بذور، والمعايرة، وSHAP، والتصدير، والنشر في الخدمة | `champion` أول قابل للترقية | Recall ≥ 85% مع Precision ≥ 80% على الاختبار، وp95 أقل من 100ms، وسيناريو أحمد ينتهي بالإيقاف |
| **P4 — المراقبة** (3 أيام) | PSI يومي، وإرسال `drift_detected`، وجدولة إعادة التدريب | cron/scheduler | انحراف مصطنع يولد إشعاراً للمدير |
| **P5 — الشذوذ والعصبية** (4 أيام) | Isolation Forest وAutoencoder وMLP للمقارنة | تشغيلات مسجلة | ظهورها في مقارنة النماذج |
| **P6 — التسلسل والرسم البياني** (8 أيام) | GRU على تسلسل الأحداث، وGraphSAGE على شبكة الحساب والجهاز والمستفيد، وembeddings دورية | مخزن embeddings + تشغيلات | تحسن PR-AUC على سيناريو mule وshared-device |
| **P7 — التجميع والتسليم** (4 أيام) | Stacking حسب توصية الملحق، ومقارنة نهائية بالأوزان، وتقرير، وتعديلات Laravel L1–L8 حسب الأولوية | تقرير المقارنة + البطل النهائي | الدرجة الموزونة الأعلى مع p95 أقل من 300ms |

المدة التقديرية: نحو 33 يوم عمل لشخص واحد، ويمكن توازي P5 مع P4، وP6 مع P5.

### توزيع مقترح لسرب الوكلاء (مثل مشروع الويب)

| الموجة | الوكيل | المراحل |
|---|---|---|
| 1 | Scaffold & Contract | P0 |
| 2 | Data & Features | P1 |
| 2 | Laravel-side | L1، L2، L3، L7 (داخل هذا المستودع) |
| 3 | Classical & Boosting | P2، P3 |
| 3 | Monitoring | P4 |
| 4 | Deep & Graph | P5، P6 |
| 5 | Ensemble & QA | P7 + اختبار التكامل الكامل |

---

## 12. المخاطر

| الخطر | الأثر | المعالجة |
|---|---|---|
| انحراف بين خصائص التدريب والتشغيل | نموذج جيد على الورق ضعيف في الإنتاج | التدريب على `risk_scores.features` + اختبار تطابق في P1 |
| تسرب البيانات (قوائم سوداء، تقسيم عشوائي، وسوم مستقبلية) | مقاييس مضللة | استبعاد `blacklist_hit` و`whitelist_hit` و`account_frozen`، وتقسيم زمني صارم |
| البيانات الاصطناعية سهلة للنموذج | Recall شبه كامل غير واقعي | الاعتماد على PaySim وIEEE-CIS في المقارنة، وإضافة ضجيج وسيناريوهات مختلطة للمحاكي |
| احتمال غير معاير | دمج خاطئ مع القواعد وعتبات غير منطقية | المعايرة إلزامية في P3 |
| تجاوز مهلة 250ms | رجوع دائم للقواعد بصمت | قياس p95 في CI، ومراقبة نسبة `used_fallback` في صحة النظام |
| GNN بطيء أو يحتاج الشبكة كاملة | لا يصلح لحظياً | embeddings مسبقة دورياً، ولا رسم بياني داخل الطلب |
| خصوصية البيانات | مخالفة PDPL | خصائص رقمية فقط، وقراءة فقط من القاعدة، ولا تسجيل لبيانات شخصية |
| موارد التدريب | بطء الشبكات العميقة | Google Colab أو Kaggle GPU كما في الوثيقة للمراحل P5 وP6 |

---

---

## ملاحظة من المستخدم

 اذا كانت العملية تتطلب تدريب نماذج بعدد دورات محدد Epoch يطلب عمل احصائيات شاملة عنمعلومات التدريب على شكل مخططات بيانية وحجداول لكل عدد محدد من دورات التدريب فرضا كل عشرة دورات تدريب من اجل توثيق عملية التدربي واعطاء سبب واخر اصائية لعمية التدريب المعتمدة في المشروع

---

## 13. قائمة التحقق قبل الربط النهائي

> تم التحقق في P7 (2026-11-01) على Laravel عبر `php artisan serve` على 8765 والخدمة على 8001؛ التفاصيل والأرقام في `reports/integration/integration.md` في مشروع Python.

- [x] `AI_DRIVER=http` و`AI_SERVICE_URL` و`AI_SERVICE_TOKEN` مضبوطة، و`GET /health` يعيد 200 (تحقق: `{"status":"ok","model_name":"xgboost"...}`).
- [x] صفحة «صحة النظام» تعرض خدمة الذكاء up (`SystemHealthService::aiStatus()` = `up`).
- [x] `php artisan bank:simulate --scenario=normal --count=300 --reset`: نسبة `used_fallback` = 0/300 (0%) أقل من 1%.
- [x] سيناريو أحمد ينتهي بالإيقاف (BLOCK والحساب FROZEN)، وأسباب `model:*` مخزنة بالعربية في `risk_scores.reasons`، و`model_run_id` = تشغيل xgboost البطل.
- [ ] البطل مسجل في `model_runs` (تم، 13 نموذجاً) لكن الترقية من لوحة المدير وظهورها في سجل التدقيق خطوة بشرية لم تُنفذ؛ مهمة `ReloadAiChampion` اختُبرت يدوياً ونجحت (`model.reload_requested` في التدقيق).
- [x] `drift_detected`: تحقق مقبول سابقاً في P4 (إشعار للمدير)، وأُعيد في P7 تشغيل `python -m monitoring.drift --model current --window-hours 24 --dry-run` على MySQL بنجاح؛ حُذفت بيانات الاختبار (`drift-acceptance-test` وإشعارها).
- [x] أداة MCP `get_model_comparison` تعرض المقارنة بالأوزان (13 نموذجاً مرتبة بالدرجة الموزونة).
