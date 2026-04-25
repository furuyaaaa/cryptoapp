<?php

namespace Database\Factories;

use App\Models\Exchange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exchange>
 */
class ExchangeFactory extends Factory
{
    protected $model = Exchange::class;

    public function definition(): array
    {
        $code = strtolower($this->faker->unique()->lexify('????'));

        return [
            'name' => ucfirst($code).' Exchange',
            'code' => $code,
            'country' => 'JP',
        ];
    }
}
