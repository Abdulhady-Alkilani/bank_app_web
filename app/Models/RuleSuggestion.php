<?php

namespace App\Models;

use App\Enums\RuleSuggestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['suggested_by', 'title', 'description', 'conditions', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'rule_id'])]
class RuleSuggestion extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'status' => RuleSuggestionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function suggester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }
}
