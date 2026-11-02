<?php

namespace App\Models;

use App\Enums\WatchlistEntity;
use App\Enums\WatchlistType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['list_type', 'entity_type', 'entity_value', 'reason', 'added_at'])]
class Watchlist extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'list_type' => WatchlistType::class,
            'entity_type' => WatchlistEntity::class,
            'added_at' => 'datetime',
        ];
    }
}
