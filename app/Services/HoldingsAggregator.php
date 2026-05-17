<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * 取引コレクションから銘柄別の保有量・評価額・損益を集計する（ダッシュボード／ポートフォリオ一覧で共通）。
 */
final class HoldingsAggregator
{
    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array<string, mixed>>
     */
    public function aggregate(Collection $transactions): array
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

        return array_values($holdings);
    }
}
