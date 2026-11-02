# AI service integration contract (Python / FastAPI)

The Laravel app scores every event with rules plus an optional model. The model lives in a separate Python project. Laravel talks to it through `App\Contracts\AiScoringClient`; the HTTP implementation is `app/Services/Ai/HttpAiScoringClient.php`. If the service is slow, down or returns garbage, Laravel falls back to rules only (`risk_scores.used_fallback = true`), so the service can never block a decision.

## Switching on

```env
AI_DRIVER=http                 # fake (default, in-process) | http | none (rules only)
AI_SERVICE_URL=http://ai-service:8001
AI_SERVICE_TOKEN=change-me     # sent as Authorization: Bearer <token>
AI_TIMEOUT_MS=250              # connect + read timeout; exceeding it triggers the rules-only fallback
ML_INGEST_TOKEN=change-me-too  # token the training scripts use against Laravel
```

Final score = `rule_weight * rule_score + model_weight * probability * 100` (defaults 0.4 / 0.6, editable in admin settings). Blacklist hits force 100. The whole round trip (features + model + decision) targets less than 300 ms, so the model call should finish well under the 250 ms budget (aim for p95 below 100 ms).

## Endpoints the service must expose

### `POST /v1/score`

Headers: `Authorization: Bearer <AI_SERVICE_TOKEN>`, `Content-Type: application/json`, `Accept: application/json`.

Request:

```json
{
  "features": {
    "amount": 25000.0,
    "amount_to_avg_ratio": 12.5,
    "is_new_device": 1,
    "device_account_count": 3,
    "logins_new_device_1h": 2,
    "minutes_since_phone_change": 18,
    "minutes_since_beneficiary_added": 4,
    "beneficiary_is_new": 1,
    "beneficiary_account_age_days": 2,
    "beneficiary_fan_out_1h": 5,
    "txn_count_1h": 4,
    "location_changed": 1,
    "hour_of_day": 3,
    "limit_raised_recently": 1,
    "blacklist_hit": 0,
    "whitelist_hit": 0,
    "account_frozen": 0
  },
  "context": {
    "event_type": "transfer",
    "account_id": 12,
    "customer_id": 9,
    "transaction_id": 4812,
    "event_id": null,
    "event_time": "2026-10-31T03:12:44+00:00"
  }
}
```

Feature notes (see `app/Services/Scoring/FeatureService.php`):

| Feature | Type | Null possible |
|---|---|---|
| `amount`, `amount_to_avg_ratio` | float (0 for non-financial events) | no |
| `is_new_device`, `beneficiary_is_new`, `location_changed`, `limit_raised_recently`, `blacklist_hit`, `whitelist_hit`, `account_frozen` | 0/1 int | no |
| `device_account_count`, `logins_new_device_1h`, `beneficiary_fan_out_1h`, `txn_count_1h`, `hour_of_day` | int | no |
| `minutes_since_phone_change`, `minutes_since_beneficiary_added`, `beneficiary_account_age_days` | number | yes (no such event/beneficiary) |

Treat `null` as "missing" (impute at training time the same way). New features may be added over time: ignore unknown keys and keep your training feature list versioned. `context` is informational (never a model input) and its keys may grow.

Response `200`:

```json
{
  "probability": 0.93,
  "model_name": "xgboost-fraud",
  "model_version": "v2026.10.1",
  "top_features": [
    { "name": "amount_to_avg_ratio", "value": 12.5, "impact": 0.31 },
    { "name": "minutes_since_phone_change", "value": 18, "impact": 0.22 }
  ],
  "latency_ms": 12
}
```

- `probability`: required, float in [0, 1] (clamped by Laravel; non-numeric = invalid payload = fallback).
- `model_name` / `model_version`: strongly recommended. Laravel uses them to link the risk score (`risk_scores.model_run_id`) to the matching `ml_models.model_name` + `model_runs.version_number`; use the same values you register via the ingest endpoint below. An unregistered pair leaves `model_run_id` empty and logs a warning (the score itself is still used).
- `top_features`: list of `{name, value, impact}` (SHAP or similar, `impact` signed: positive pushes toward fraud). Shown to analysts as reasons. Up to about 5 entries.
- `latency_ms`: service-side inference time (int).

Any non-2xx status, timeout or invalid body makes Laravel use rules only.

### `POST /v1/shadow/score` (optional, shadow mode)

Enabled on the Laravel side with `AI_SHADOW_ENABLED=true` (and `AI_DRIVER=http`). After the risk score is stored, a queued job sends the same body and the same `Authorization: Bearer <AI_SERVICE_TOKEN>` as `/v1/score`, with a 2 second timeout. The response has the same shape as `/v1/score` (`probability`, `model_name`, `model_version`, ...). Laravel stores `risk_scores.shadow_probability` and `risk_scores.shadow_model_run_id` (resolved with `ModelRegistry::runFor`, so register the shadow model via the ingest endpoint). The result never affects the decision. A `404` (no shadow model loaded), any other non-2xx status, a timeout or an invalid body is ignored (logged at debug level).

### `POST /admin/reload` (champion sync)

Called by Laravel after an admin promotes a model to champion (only when `AI_DRIVER=http`), from a queued job.

Headers: `Authorization: Bearer <AI_ADMIN_TOKEN>` (the service's `ADMIN_RELOAD_TOKEN`; a different token from `AI_SERVICE_TOKEN`), `Content-Type: application/json`.

```json
{ "model_name": "xgboost-fraud", "model_version": "v2026.10.1" }
```

`model_version` is the latest registered run of the promoted model. Respond `2xx` once the version is loaded. A failure is logged and audited in Laravel (`model.reload_failed`) and never undoes the promotion; the service keeps serving its current model.

### `GET /health`

Return `200` when the model is loaded and ready (any 2xx body, e.g. `{"status":"ok","model_version":"v2026.10.1"}`). Used by the admin system-health page and Docker healthchecks. Does not need authentication, but the Bearer header may be sent.

## Training results to Laravel

Training stays outside the web app. After each run, the Python script registers the version and its metrics:

```http
POST {APP_URL}/api/v1/ml/model-runs
Authorization: Bearer <ML_INGEST_TOKEN>
Content-Type: application/json
```

```json
{
  "model_name": "xgboost-fraud",
  "algorithm": "XGBoost",
  "version_number": "v2026.10.1",
  "trained_at": "2026-10-30T22:15:00Z",
  "metrics": {
    "pr_auc": 0.81,
    "recall_at_p90": 0.64,
    "precision_at_k": 0.88,
    "f1": 0.72,
    "f2": 0.69,
    "roc_auc": 0.97,
    "interpretability": 0.7,
    "p95_latency_ms": 38,
    "pr_auc_std": 0.012,
    "resource_score": 0.8,
    "drift_detected": false
  }
}
```

Response `201` with the stored run. A model seen for the first time is created as `candidate`; promoting it to `champion` is a manual, audited admin action (admin UI). `drift_detected: true` notifies all active admins (SYS-09).

### Metric keys used by the dashboard (`app/Support/ModelScore.php`)

Final score (0-100) is the weighted sum over the metrics that are present (weights are renormalised over the present ones):

| Key | Weight | Scale |
|---|---|---|
| `pr_auc` | 30% | 0-1 |
| `recall_at_p90` (recall at precision 90%) | 20% | 0-1 |
| `precision_at_k` | 15% | 0-1 |
| `interpretability` | 10% | 0-1 (your judgement/rubric) |
| `p95_latency_ms` | 10% | milliseconds; scored as `1 - latency/300`; above 300 ms the model is flagged "excluded from real-time path" |
| `stability` | 10% | 0-1; if missing but `pr_auc_std` is sent, `stability = 1 - pr_auc_std` (std of PR-AUC across seeds) |
| `resource_score` | 5% | 0-1 (higher is cheaper) |
| `f1`, `f2`, `roc_auc` | 0% | displayed only |

Accepted aliases: `auc` = `roc_auc`, `recall` = `recall_at_p90`, `precision` = `precision_at_k`, `latency_p95_ms` / `p95_latency` = `p95_latency_ms`, `resource_cost` = `resource_score`. Extra keys are stored untouched.

## Training labels

Labelled data comes from Laravel's `labels` table (analyst decisions, bank `POST /api/v1/verification-results`, customer OTP outcomes). The Python project reads it directly from the database (read-only user recommended) or from an export. Features at scoring time are stored in `risk_scores.features`, so training uses exactly the values the model saw online (no train/serve skew).

## Docker

`docker-compose.yml` contains an `ai-service` service (port 8001) under the compose profile `ai`, so it never blocks a normal start. The build context defaults to `../ai_model` (override with `AI_SERVICE_BUILD_CONTEXT`). Start it with `docker compose --profile ai up -d --build` and set in `.env`:

```env
AI_DRIVER=http
AI_SERVICE_URL=http://ai-service:8001
AI_SERVICE_TOKEN=...
AI_ADMIN_TOKEN=...             # = ADMIN_RELOAD_TOKEN of the service
AI_SHADOW_ENABLED=false
```
