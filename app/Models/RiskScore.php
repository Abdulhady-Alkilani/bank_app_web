<?php

namespace App\Models;

use App\Enums\Decision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['transaction_id', 'event_id', 'model_run_id', 'score', 'decision', 'reasons', 'evaluated_at', 'rule_score', 'model_probability', 'used_fallback', 'latency_ms', 'features', 'shadow_probability', 'shadow_model_run_id'])]
class RiskScore extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'decision' => Decision::class,
            'reasons' => 'array',
            'features' => 'array',
            'evaluated_at' => 'datetime',
            'rule_score' => 'integer',
            'model_probability' => 'decimal:4',
            'shadow_probability' => 'decimal:4',
            'used_fallback' => 'boolean',
            'latency_ms' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AccountEvent::class, 'event_id');
    }

    public function modelRun(): BelongsTo
    {
        return $this->belongsTo(ModelRun::class);
    }

    public function shadowModelRun(): BelongsTo
    {
        return $this->belongsTo(ModelRun::class, 'shadow_model_run_id');
    }

    public function alert(): HasOne
    {
        return $this->hasOne(Alert::class);
    }
}
