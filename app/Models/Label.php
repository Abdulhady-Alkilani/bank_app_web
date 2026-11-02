<?php

namespace App\Models;

use App\Enums\LabelSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transaction_id', 'event_id', 'alert_id', 'is_fraud', 'source', 'labeled_by'])]
class Label extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_fraud' => 'boolean',
            'source' => LabelSource::class,
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

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function labeler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'labeled_by');
    }
}
