<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\AssetStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AssetStatsService $stats): Response
    {
        $user = $request->user();

        $portfolios = $user->portfolios()
            ->with([
                'transactions.asset.latestPrice',
            ])
            ->get();

        $allTransactions = $portfolios->flatMap->transactions;
        $holdings = $this->calculateHoldings($allTransactions);

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
        $statsByAsset = $stats->forAssets($assetIds);

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

        return Inertia::render('Dashboard', [
            'totals' => $totals,
            'allocation' => array_values($allocation),
            'topHoldings' => array_values($topHoldings),
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Transaction>  $transactions
     * @return array<int, array<string, mixed>>
     */
    private function calculateHoldings($transactions): array
    {
        $byAsset = [];

        foreach ($transactions as $tx) {
            $assetId = $tx->asset_id;

            if (! isset($byAsset[$assetId])) {
                $byAsset[$assetId] = [
                    'asset_id' => $assetId,
                    'symbol' => $tx->asset->symbol,
                    'name' => $tx->asset->name,
                    'icon_url' => $tx->asset->icon_url,
                    'total_in_amount' => 0.0,
                    'total_in_cost' => 0.0,
                    'total_out_amount' => 0.0,
                    'current_price_jpy' => (float) ($tx->asset->latestPrice?->price_jpy ?? 0),
                ];
            }

            $amount = (float) $tx->amount;
            $price = (float) $tx->price_jpy;
            $fee = (float) $tx->fee_jpy;

            if (in_array($tx->type, [Transaction::TYPE_BUY, Transaction::TYPE_TRANSFER_IN], true)) {
                $byAsset[$assetId]['total_in_amount'] += $amount;
                $byAsset[$assetId]['total_in_cost'] += $amount * $price + $fee;
            } else {
                $byAsset[$assetId]['total_out_amount'] += $amount;
            }
        }

        foreach ($byAsset as &$h) {
            $currentAmount = $h['total_in_amount'] - $h['total_out_amount'];
            $avgBuyPrice = $h['total_in_amount'] > 0
                ? $h['total_in_cost'] / $h['total_in_amount']
                : 0.0;
            $costBasis = $currentAmount * $avgBuyPrice;
            $valuation = $currentAmount * $h['current_price_jpy'];
            $profit = $valuation - $costBasis;

            $h['amount'] = $currentAmount;
            $h['avg_buy_price'] = $avgBuyPrice;
            $h['cost_basis'] = $costBasis;
            $h['valuation'] = $valuation;
            $h['profit'] = $profit;
            $h['profit_rate'] = $costBasis > 0 ? $profit / $costBasis : 0;

            unset($h['total_in_amount'], $h['total_in_cost'], $h['total_out_amount']);
        }
        unset($h);

        $holdings = array_filter($byAsset, fn ($h) => $h['amount'] > 0.00000001);

        usort($holdings, fn ($a, $b) => $b['valuation'] <=> $a['valuation']);

        return $holdings;
    }
}
