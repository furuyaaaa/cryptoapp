<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortfolioRequest;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Services\AssetStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function index(Request $request, AssetStatsService $stats): Response
    {
        $user = $request->user();

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

        $statsByAsset = $stats->forAssets($allAssetIds);

        $portfolioData = $portfolios->map(function ($portfolio) use ($statsByAsset) {
            $holdings = $this->calculateHoldings($portfolio->transactions);

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

        return Inertia::render('Portfolios/Index', [
            'portfolios' => $portfolioData,
            'totals' => $totals,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Portfolios/Create');
    }

    public function store(PortfolioRequest $request): RedirectResponse
    {
        $request->user()->portfolios()->create($request->validated());

        return redirect()
            ->route('portfolios.index')
            ->with('success', 'ポートフォリオを作成しました。');
    }

    public function edit(Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        return Inertia::render('Portfolios/Edit', [
            'portfolio' => $portfolio->only(['id', 'name', 'description']),
        ]);
    }

    public function update(PortfolioRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->update($request->validated());

        return redirect()
            ->route('portfolios.index')
            ->with('success', 'ポートフォリオを更新しました。');
    }

    public function destroy(Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return redirect()
            ->route('portfolios.index')
            ->with('success', 'ポートフォリオを削除しました。');
    }

    /**
     * Aggregate transactions by asset to compute holdings with average cost basis.
     *
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
