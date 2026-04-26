<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;

/**
 * ダッシュボード用の Inertia / JSON 共通ペイロード。
 */
final class DashboardDataService
{
    public function __construct(
        private readonly HoldingsAggregator $holdings,
        private readonly AssetStatsService $stats,
    ) {}

    /**
     * @return array{totals: array<string, mixed>, allocation: list<array<string, mixed>>, topHoldings: list<array<string, mixed>>, recentTransactions: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    public function buildForUser(User $user): array
    {
        $portfolios = $user->portfolios()
            ->with([
                'transactions.asset.latestPrice',
            ])
            ->get();

        $allTransactions = $portfolios->flatMap->transactions;
        $holdings = $this->holdings->aggregate($allTransactions);

        $valuation = array_sum(array_column($holdings, 'valuation'));
        $costBasis = array_sum(array_column($holdings, 'cost_basis'));
        $profit = $valuation - $costBasis;

        $totals = [
            'valuation' => $valuation,
            'cost_basis' => $costBasis,
            'profit' => $profit,
            'profit_rate' => $costBasis > 0 ? $profit / $costBasis : 0,
            'portfolios_count' => $portfolios->count(),
            'transactions_count' => $allTransactions->count(),
            'assets_count' => count($holdings),
        ];

        $allocation = array_map(fn ($h) => [
            'symbol' => $h['symbol'],
            'name' => $h['name'],
            'valuation' => $h['valuation'],
            'share' => $valuation > 0 ? $h['valuation'] / $valuation : 0,
        ], $holdings);

        $assetIds = array_column($holdings, 'asset_id');
        $statsByAsset = $this->stats->forAssets($assetIds);

        $topHoldings = array_map(
            function ($h) use ($statsByAsset) {
                $s = $statsByAsset[$h['asset_id']] ?? null;

                return [
                    ...$h,
                    'icon_url' => $h['icon_url'] ?? null,
                    'change_24h' => $s['change_24h'] ?? null,
                    'sparkline' => $s['sparkline'] ?? [],
                ];
            },
            array_slice($holdings, 0, 5)
        );

        $recentTransactions = Transaction::query()
            ->whereIn('portfolio_id', $portfolios->pluck('id'))
            ->with(['asset', 'portfolio', 'exchange'])
            ->orderByDesc('executed_at')
            ->limit(8)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
                'price_jpy' => (float) $tx->price_jpy,
                'fee_jpy' => (float) $tx->fee_jpy,
                'executed_at' => $tx->executed_at?->toIso8601String(),
                'asset' => [
                    'symbol' => $tx->asset->symbol,
                    'name' => $tx->asset->name,
                    'icon_url' => $tx->asset->icon_url,
                ],
                'portfolio' => [
                    'id' => $tx->portfolio->id,
                    'name' => $tx->portfolio->name,
                ],
                'exchange' => $tx->exchange ? [
                    'name' => $tx->exchange->name,
                ] : null,
            ]);

        return [
            'totals' => $totals,
            'allocation' => array_values($allocation),
            'topHoldings' => array_values($topHoldings),
            'recentTransactions' => $recentTransactions,
        ];
    }
}
