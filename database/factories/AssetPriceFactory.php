<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetPrice>
 */
class AssetPriceFactory extends Factory
{
    protected $model = AssetPrice::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'price_jpy' => $this->faker->randomFloat(2, 100, 10_000_000),
            'price_usd' => $this->faker->randomFloat(2, 1, 100_000),
            'recorded_at' => now(),
        ];
    }
}
