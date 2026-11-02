<?php

namespace App\Models;

use App\Enums\AlertStatus;
use App\Enums\AnalystDecision;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['case_id', 'risk_score_id', 'status', 'priority', 'assigned_to', 'decision', 'decision_reason', 'decided_by', 'decided_at'])]
class Alert extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => AlertStatus::class,
            'priority' => Priority::class,
            'decision' => AnalystDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(InvestigationCase::class, 'case_id');
    }

    public function riskScore(): BelongsTo
    {
        return $this->belongsTo(RiskScore::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }
}
