<?php

namespace App\Models;

use App\Enums\OtpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'transaction_id', 'event_id', 'code_hash', 'status', 'attempts', 'expires_at', 'verified_at'])]
#[Hidden(['code_hash'])]
class OtpChallenge extends Model
{
    use HasFactory;

    protected $table = 'otp_challenges';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => OtpStatus::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AccountEvent::class, 'event_id');
    }
}
