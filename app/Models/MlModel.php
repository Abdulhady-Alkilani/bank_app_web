<?php

namespace App\Models;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['model_name', 'algorithm', 'status'])]
class MlModel extends Model
{
    use HasFactory;

    protected $table = 'ml_models';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ModelStatus::class,
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ModelRun::class, 'model_id');
    }
}
