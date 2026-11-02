<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'account_number', 'type', 'status', 'balance', 'transfer_limit'])]
class Account extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'balance' => 'decimal:2',
            'transfer_limit' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AccountEvent::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AccountAction::class);
    }

    public function isFrozen(): bool
    {
        return $this->status === AccountStatus::Frozen;
    }
}
