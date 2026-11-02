<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['rule_name', 'description', 'weight', 'priority', 'is_active', 'conditions', 'action'])]
class Rule extends Model
{
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'conditions' => 'array',
        ];
    }
}
