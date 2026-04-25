<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $transactions = Transaction::query()
            ->whereHas('portfolio', fn ($q) => $q->where('user_id', $user->id))
            ->with(['asset.latestPrice', 'exchange'])
            ->orderBy('executed_at')
            ->get();

        [$exchanges, $totals] = $this->aggregateByExchange($transactions);

        return Inertia::render('Exchanges/Index', [
            'exchanges' => $exchanges,
            'totals' => $totals,
        ]);
    }

    /**
     * 取引所 × 銘柄で集計し、さらに取引所単位でロールアップする。
     *
     * @param  \Illuminate\Support\Collection<int, Transaction>  $transactions
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, float|int>}
     */
    private function aggregateByExchange($transactions): array
    {
        $exchanges = [];

        foreach ($transactions as $tx) {
            $exchangeId = $tx->exchange_id ?? 0;
            $exchangeName = $tx->exchange?->name ?? '未設定';

            if (! isset($exchanges[$exchangeId])) {
                $exchanges[$exchangeId] = [
                    'id' => $exchangeId,
                    'name' => $exchangeName,
                    'assets' => [],
                    'buy_volume_jpy' => 0.0,
                    'sell_volume_jpy' => 0.0,
                    'fee_total' => 0.0,
                    'realized_pnl' => 0.0,
                    'buy_count' => 0,
                    'sell_count' => 0,
                    'transfer_in_count' => 0,
                    'transfer_out_count' => 0,
                    'tx_count' => 0,
                    'last_tx_at' => null,
                ];
            }

            $assetId = $tx->asset_id;
            if (! isset($exchanges[$exchangeId]['assets'][$assetId])) {
                $exchanges[$exchangeId]['assets'][$assetId] = [
                    'asset_id' => $assetId,
                    'symbol' => $tx->asset->symbol,
                    'name' => $tx->asset->name,
                    'icon_url' => $tx->asset->icon_url,
                    'current_price_jpy' => (float) ($tx->asset->latestPrice?->price_jpy ?? 0),
                    'in_amount' => 0.0,
                    'in_cost' => 0.0,
                    'out_amount' => 0.0,
                ];
            }

            $amount = (float) $tx->amount;
            $price = (float) $tx->price_jpy;
            $fee = (float) $tx->fee_jpy;
            $gross = $amount * $price;

            $asset = &$exchanges[$exchangeId]['assets'][$assetId];
            $ex = &$exchanges[$exchangeId];

            $ex['fee_total'] += $fee;
            $ex['tx_count'] += 1;
            $executedAt = $tx->executed_at?->toIso8601String();
            if ($executedAt && (! $ex['last_tx_at'] || $executedAt > $ex['last_tx_at'])) {
                $ex['last_tx_at'] = $executedAt;
            }

            switch ($tx->type) {
                case Transaction::TYPE_BUY:
                    $asset['in_amount'] += $amount;
                    $asset['in_cost'] += $gross + $fee;
                    $ex['buy_volume_jpy'] += $gross;
                    $ex['buy_count'] += 1;
                    break;

                case Transaction::TYPE_SELL:
                    $avgCost = $asset['in_amount'] > 0
                        ? $asset['in_cost'] / $asset['in_amount']
                        : 0.0;
                    $ex['realized_pnl'] += ($price - $avgCost) * $amount - $fee;
                    $asset['out_amount'] += $amount;
                    $ex['sell_volume_jpy'] += $gross;
                    $ex['sell_count'] += 1;
                    break;

                case Transaction::TYPE_TRANSFER_IN:
                    $asset['in_amount'] += $amount;
                    $asset['in_cost'] += $gross + $fee;
                    $ex['transfer_in_count'] += 1;
                    break;

                case Transaction::TYPE_TRANSFER_OUT:
                    $asset['out_amount'] += $amount;
                    $ex['transfer_out_count'] += 1;
                    break;
            }
            unset($asset, $ex);
        }

        $result = [];
        foreach ($exchanges as $ex) {
            $holdings = [];
            $valuation = 0.0;
            $costBasis = 0.0;

            foreach ($ex['assets'] as $a) {
                $currentAmount = $a['in_amount'] - $a['out_amount'];
                if ($currentAmount <= 0.00000001) {
                    continue;
                }
                $avgBuyPrice = $a['in_amount'] > 0 ? $a['in_cost'] / $a['in_amount'] : 0.0;
                $assetCost = $currentAmount * $avgBuyPrice;
                $assetValuation = $currentAmount * $a['current_price_jpy'];
                $assetProfit = $assetValuation - $assetCost;

                $holdings[] = [
                    'asset_id' => $a['asset_id'],
                    'symbol' => $a['symbol'],
                    'name' => $a['name'],
                    'icon_url' => $a['icon_url'],
                    'amount' => $currentAmount,
                    'avg_buy_price' => $avgBuyPrice,
                    'current_price_jpy' => $a['current_price_jpy'],
                    'cost_basis' => $assetCost,
                    'valuation' => $assetValuation,
                    'profit' => $assetProfit,
                    'profit_rate' => $assetCost > 0 ? $assetProfit / $assetCost : 0,
                ];

                $valuation += $assetValuation;
                $costBasis += $assetCost;
            }

            usort($holdings, fn ($a, $b) => $b['valuation'] <=> $a['valuation']);

            $profit = $valuation - $costBasis;
            $tradeVolume = $ex['buy_volume_jpy'] + $ex['sell_volume_jpy'];

            $result[] = [
                'id' => $ex['id'],
                'name' => $ex['name'],
                'valuation' => $valuation,
                'cost_basis' => $costBasis,
                'profit' => $profit,
                'profit_rate' => $costBasis > 0 ? $profit / $costBasis : 0,
                'realized_pnl' => $ex['realized_pnl'],
                'trade_volume_jpy' => $tradeVolume,
                'buy_volume_jpy' => $ex['buy_volume_jpy'],
                'sell_volume_jpy' => $ex['sell_volume_jpy'],
                'fee_total' => $ex['fee_total'],
                'buy_count' => $ex['buy_count'],
                'sell_count' => $ex['sell_count'],
                'transfer_in_count' => $ex['transfer_in_count'],
                'transfer_out_count' => $ex['transfer_out_count'],
                'tx_count' => $ex['tx_count'],
                'assets_count' => count($holdings),
                'last_tx_at' => $ex['last_tx_at'],
                'holdings' => $holdings,
            ];
        }

        usort($result, fn ($a, $b) => $b['valuation'] <=> $a['valuation']);

        $totals = [
            'valuation' => array_sum(array_column($result, 'valuation')),
            'cost_basis' => array_sum(array_column($result, 'cost_basis')),
            'profit' => array_sum(array_column($result, 'profit')),
            'realized_pnl' => array_sum(array_column($result, 'realized_pnl')),
            'trade_volume_jpy' => array_sum(array_column($result, 'trade_volume_jpy')),
            'buy_volume_jpy' => array_sum(array_column($result, 'buy_volume_jpy')),
            'sell_volume_jpy' => array_sum(array_column($result, 'sell_volume_jpy')),
            'fee_total' => array_sum(array_column($result, 'fee_total')),
            'tx_count' => array_sum(array_column($result, 'tx_count')),
            'exchanges_count' => count($result),
        ];
        $totals['profit_rate'] = $totals['cost_basis'] > 0
            ? $totals['profit'] / $totals['cost_basis']
            : 0;

        return [$result, $totals];
    }
}
