<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['model_id', 'version_number', 'metrics', 'trained_at'])]
class ModelRun extends Model
{
    use HasFactory;

    protected $table = 'model_runs';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'trained_at' => 'datetime',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(MlModel::class, 'model_id');
    }

    public function riskScores(): HasMany
    {
        return $this->hasMany(RiskScore::class);
    }
}
