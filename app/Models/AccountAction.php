<?php

namespace App\Models;

use App\Enums\AccountActionStatus;
use App\Enums\AccountActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'transaction_id', 'alert_id', 'approval_id', 'beneficiary_id', 'action', 'status', 'requested_by', 'reason', 'executed_at'])]
class AccountAction extends Model
{
    use HasFactory;

    protected $table = 'account_actions';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'action' => AccountActionType::class,
            'status' => AccountActionStatus::class,
            'executed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
