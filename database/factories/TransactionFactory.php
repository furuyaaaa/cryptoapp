<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'asset_id' => Asset::factory(),
            'exchange_id' => null,
            'type' => Transaction::TYPE_BUY,
            'amount' => $this->faker->randomFloat(4, 0.01, 10),
            'price_jpy' => $this->faker->randomFloat(2, 100, 10_000_000),
            'fee_jpy' => 0,
            'executed_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'note' => null,
        ];
    }

    public function buy(): static
    {
        return $this->state(['type' => Transaction::TYPE_BUY]);
    }

    public function sell(): static
    {
        return $this->state(['type' => Transaction::TYPE_SELL]);
    }
}
