<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DemoController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => [
                    'name' => 'Demo User',
                    'email' => 'demo@example.com',
                ],
                'is_admin' => false,
                'two_factor_enabled' => false,
                'two_factor_pending' => false,
            ],
            'totals' => [
                'valuation' => 12_450_780,
                'cost_basis' => 10_120_500,
                'profit' => 2_330_280,
                'profit_rate' => 0.230252458,
                'portfolios_count' => 3,
                'assets_count' => 9,
                'transactions_count' => 42,
            ],
            'allocation' => [
                ['symbol' => 'BTC', 'name' => 'Bitcoin', 'valuation' => 4_750_000, 'share' => 0.3815],
                ['symbol' => 'ETH', 'name' => 'Ethereum', 'valuation' => 3_120_000, 'share' => 0.2506],
                ['symbol' => 'SOL', 'name' => 'Solana', 'valuation' => 1_750_000, 'share' => 0.1406],
                ['symbol' => 'XRP', 'name' => 'XRP', 'valuation' => 1_100_000, 'share' => 0.0883],
                ['symbol' => 'USDT', 'name' => 'Tether', 'valuation' => 910_780, 'share' => 0.0732],
                ['symbol' => 'Others', 'name' => 'Others', 'valuation' => 820_000, 'share' => 0.0658],
            ],
            'topHoldings' => [
                $this->holding(1, 'BTC', 'Bitcoin', 0.52, 4_750_000, 2_430_000, 1.047, 0.018),
                $this->holding(2, 'ETH', 'Ethereum', 8.4, 3_120_000, 620_000, 0.248, -0.006),
                $this->holding(3, 'SOL', 'Solana', 215, 1_750_000, 480_000, 0.378, 0.031),
                $this->holding(4, 'XRP', 'XRP', 6200, 1_100_000, -120_000, -0.098, 0.012),
                $this->holding(5, 'USDT', 'Tether', 5800, 910_780, 10_780, 0.012, 0.001),
            ],
            'recentTransactions' => [
                $this->transaction(1, 'buy', 'BTC', 'Bitcoin', 1, 'Main Portfolio', 'bitFlyer', 0.025, 6_328_000, '2026-06-06T14:15:00+09:00'),
                $this->transaction(2, 'sell', 'ETH', 'Ethereum', 2, 'Long Term', 'Coincheck', 0.51, 264_118, '2026-06-06T09:30:00+09:00'),
                $this->transaction(3, 'buy', 'SOL', 'Solana', 3, 'Growth', 'Binance', 10, 9_840, '2026-06-05T18:42:00+09:00'),
                $this->transaction(4, 'buy', 'XRP', 'XRP', 4, 'Main Portfolio', 'bitbank', 300, 95, '2026-06-05T11:03:00+09:00'),
                $this->transaction(5, 'transfer_in', 'USDT', 'Tether', 5, 'Stable Reserve', null, 850, 146, '2026-06-04T21:18:00+09:00'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function holding(
        int $assetId,
        string $symbol,
        string $name,
        float $amount,
        int $valuation,
        int $profit,
        float $profitRate,
        float $change24h,
    ): array {
        return [
            'asset_id' => $assetId,
            'symbol' => $symbol,
            'name' => $name,
            'amount' => $amount,
            'valuation' => $valuation,
            'cost_basis' => $valuation - $profit,
            'profit' => $profit,
            'profit_rate' => $profitRate,
            'icon_url' => null,
            'change_24h' => $change24h,
            'sparkline' => [18, 21, 19, 24, 23, 27, 29, 28, 32, 35, 34, 38],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(
        int $id,
        string $type,
        string $symbol,
        string $name,
        int $assetId,
        string $portfolioName,
        ?string $exchangeName,
        float $amount,
        int $priceJpy,
        string $executedAt,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'amount' => $amount,
            'price_jpy' => $priceJpy,
            'fee_jpy' => 0,
            'executed_at' => $executedAt,
            'asset' => [
                'id' => $assetId,
                'symbol' => $symbol,
                'name' => $name,
                'icon_url' => null,
            ],
            'portfolio' => [
                'id' => $id,
                'name' => $portfolioName,
            ],
            'exchange' => $exchangeName ? ['name' => $exchangeName] : null,
        ];
    }
}
