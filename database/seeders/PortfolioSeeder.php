<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Exchange;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstWhere('email', 'test@example.com');
        if (! $user) {
            $this->command->warn('Test user not found, skip PortfolioSeeder');

            return;
        }

        $portfolio = Portfolio::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'メインポートフォリオ'],
            ['description' => '長期保有用。サンプルデータです。']
        );

        if ($portfolio->transactions()->exists()) {
            return;
        }

        $exchange = Exchange::firstWhere('code', 'bitflyer');

        $samples = [
            ['symbol' => 'BTC',  'type' => 'buy', 'amount' => 0.05,   'price_jpy' => 5_000_000,  'days_ago' => 180],
            ['symbol' => 'BTC',  'type' => 'buy', 'amount' => 0.02,   'price_jpy' => 8_500_000,  'days_ago' => 60],
            ['symbol' => 'ETH',  'type' => 'buy', 'amount' => 1.0,    'price_jpy' => 280_000,    'days_ago' => 150],
            ['symbol' => 'ETH',  'type' => 'buy', 'amount' => 0.5,    'price_jpy' => 400_000,    'days_ago' => 30],
            ['symbol' => 'XRP',  'type' => 'buy', 'amount' => 1000,   'price_jpy' => 80,         'days_ago' => 200],
            ['symbol' => 'XRP',  'type' => 'sell','amount' => 300,    'price_jpy' => 250,        'days_ago' => 10],
            ['symbol' => 'SOL',  'type' => 'buy', 'amount' => 10,     'price_jpy' => 15_000,     'days_ago' => 90],
            ['symbol' => 'DOGE', 'type' => 'buy', 'amount' => 5000,   'price_jpy' => 25,         'days_ago' => 120],
        ];

        foreach ($samples as $s) {
            $asset = Asset::firstWhere('symbol', $s['symbol']);
            if (! $asset) {
                continue;
            }

            Transaction::create([
                'portfolio_id' => $portfolio->id,
                'asset_id' => $asset->id,
                'exchange_id' => $exchange?->id,
                'type' => $s['type'],
                'amount' => $s['amount'],
                'price_jpy' => $s['price_jpy'],
                'fee_jpy' => round($s['amount'] * $s['price_jpy'] * 0.0015, 2),
                'executed_at' => Carbon::now()->subDays($s['days_ago']),
            ]);
        }

        $this->command->info(sprintf('Created portfolio "%s" with %d sample transactions.', $portfolio->name, count($samples)));
    }
}
