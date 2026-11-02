<?php

namespace Database\Factories;

use App\Enums\WatchlistEntity;
use App\Enums\WatchlistType;
use App\Models\Watchlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Watchlist>
 */
class WatchlistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'list_type' => WatchlistType::Blacklist,
            'entity_type' => WatchlistEntity::Account,
            'entity_value' => fake()->numerify('##########'),
            'reason' => fake()->sentence(),
            'added_at' => now(),
        ];
    }
}
