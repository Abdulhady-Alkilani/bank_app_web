<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'phone', 'email', 'avg_balance', 'usage_pattern'])]
class Customer extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'avg_balance' => 'decimal:2',
            'usage_pattern' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function customerDevices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'customer_devices')->withPivot(['is_trusted', 'last_used_at'])->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CustomerReport::class);
    }
}
