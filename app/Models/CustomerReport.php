<?php

namespace App\Models;

use App\Enums\CustomerReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'transaction_id', 'description', 'status', 'handled_by', 'handled_at'])]
class CustomerReport extends Model
{
    use HasFactory;

    protected $table = 'customer_reports';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerReportStatus::class,
            'handled_at' => 'datetime',
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

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
