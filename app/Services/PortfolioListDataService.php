<?php

namespace App\Services;

use App\Models\User;

/**
 * ポートフォリオ一覧（Inertia / JSON 共通）。
 */
final class PortfolioListDataService
{
    public function __construct(
        private readonly HoldingsAggregator $holdings,
        private readonly AssetStatsService $stats,
    ) {}

    /**
     * @return array{portfolios: \Illuminate\Support\Collection<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function buildForUser(User $user): array
    {
        $portfolios = $user->portfolios()
            ->with([
                'transactions.asset.latestPrice',
                'transactions.exchange',
            ])
            ->orderBy('created_at')
            ->get();

        $allAssetIds = $portfolios->flatMap->transactions
            ->pluck('asset_id')
            ->unique()
            ->values()
            ->all();

        $statsByAsset = $this->stats->forAssets($allAssetIds);

        $portfolioData = $portfolios->map(function ($portfolio) use ($statsByAsset) {
            $holdings = $this->holdings->aggregate($portfolio->transactions);

            $holdings = array_map(function ($h) use ($statsByAsset) {
                $s = $statsByAsset[$h['asset_id']] ?? null;

                return [
                    ...$h,
                    'change_24h' => $s['change_24h'] ?? null,
                    'sparkline' => $s['sparkline'] ?? [],
                ];
            }, $holdings);

            $valuation = array_sum(array_column($holdings, 'valuation'));
            $costBasis = array_sum(array_column($holdings, 'cost_basis'));
            $profit = $valuation - $costBasis;

            return [
                'id' => $portfolio->id,
                'name' => $portfolio->name,
                'description' => $portfolio->description,
                'valuation' => $valuation,
                'cost_basis' => $costBasis,
                'profit' => $profit,
                'profit_rate' => $costBasis > 0 ? $profit / $costBasis : 0,
                'holdings' => array_values($holdings),
            ];
        });

        $totals = [
            'valuation' => $portfolioData->sum('valuation'),
            'cost_basis' => $portfolioData->sum('cost_basis'),
            'profit' => $portfolioData->sum('profit'),
        ];
        $totals['profit_rate'] = $totals['cost_basis'] > 0
            ? $totals['profit'] / $totals['cost_basis']
            : 0;

        return [
            'portfolios' => $portfolioData,
            'totals' => $totals,
        ];
    }
}
