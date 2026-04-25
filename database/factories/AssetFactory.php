<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $symbol = strtoupper($this->faker->unique()->lexify('???'));

        return [
            'symbol' => $symbol,
            'name' => $symbol.' Coin',
            'coingecko_id' => strtolower($symbol).'-'.$this->faker->unique()->numberBetween(1, 1_000_000),
            'icon_url' => null,
        ];
    }
}
