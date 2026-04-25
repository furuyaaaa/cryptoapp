<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * 日本の暗号資産 税務計算サービス
 *
 * 対応する評価方法:
 *  - 移動平均法 (moving_average): 購入の都度、加重平均取得単価を更新。売却時に実現損益を確定。
 *  - 総平均法   (total_average) : 期初在庫 + 期中の総購入 を元に年単位の平均取得単価を算定。
 *
 * いずれも手数料は取得価額（買い）または譲渡費用（売り）に算入する。
 * transfer_in / transfer_out はコストベースに影響しない（取引所間の単純移動として扱う）。
 */
class TaxCalculationService
{
    public const METHOD_MOVING_AVERAGE = 'moving_average';
    public const METHOD_TOTAL_AVERAGE = 'total_average';

    /**
     * @param  Collection<int, Transaction>  $transactions  ユーザーの全期間の取引（時系列に関係なく可）
     * @return array{
     *   method: string,
     *   year: int,
     *   assets: array<int, array<string, mixed>>,
     *   totals: array<string, float>,
     * }
     */
    public function calculate(Collection $transactions, int $year, string $method): array
    {
        $method = in_array($method, [self::METHOD_MOVING_AVERAGE, self::METHOD_TOTAL_AVERAGE], true)
            ? $method
            : self::METHOD_MOVING_AVERAGE;

        $sorted = $transactions
            ->sortBy(fn ($tx) => [$tx->executed_at?->timestamp ?? 0, $tx->id])
            ->values();

        $byAsset = $sorted->groupBy('asset_id');

        $assets = [];
        foreach ($byAsset as $assetId => $txs) {
            $first = $txs->first();
            $asset = $first->asset;

            $result = $method === self::METHOD_TOTAL_AVERAGE
                ? $this->calculateTotalAverage($txs, $year)
                : $this->calculateMovingAverage($txs, $year);

            if ($result['sell_count'] === 0 && $result['buy_count_in_year'] === 0) {
                continue;
            }

            $assets[] = array_merge([
                'asset_id' => (int) $assetId,
                'symbol' => $asset?->symbol,
                'name' => $asset?->name,
                'icon_url' => $asset?->icon_url,
            ], $result);
        }

        usort($assets, fn ($a, $b) => $b['realized_gain'] <=> $a['realized_gain']);

        $totals = [
            'proceeds' => array_sum(array_column($assets, 'proceeds')),
            'cost_of_sold' => array_sum(array_column($assets, 'cost_of_sold')),
            'sell_fees' => array_sum(array_column($assets, 'sell_fees')),
            'realized_gain' => array_sum(array_column($assets, 'realized_gain')),
            'sell_count' => array_sum(array_column($assets, 'sell_count')),
        ];

        return [
            'method' => $method,
            'year' => $year,
            'assets' => $assets,
            'totals' => $totals,
        ];
    }

    /**
     * 移動平均法による年次実現損益。
     *
     * @param  Collection<int, Transaction>  $txs
     * @return array<string, mixed>
     */
    private function calculateMovingAverage(Collection $txs, int $year): array
    {
        $heldAmount = 0.0;
        $heldCost = 0.0;
        $avgPrice = 0.0;

        $lots = [];
        $proceeds = 0.0;
        $costOfSold = 0.0;
        $sellFees = 0.0;
        $sellCount = 0;
        $buyCountInYear = 0;
        $buyAmountInYear = 0.0;
        $buyCostInYear = 0.0;

        $openingAmount = 0.0;
        $openingCost = 0.0;
        $openingCaptured = false;

        foreach ($txs as $tx) {
            $txYear = (int) ($tx->executed_at?->format('Y') ?? 0);

            if (! $openingCaptured && $txYear >= $year) {
                $openingAmount = $heldAmount;
                $openingCost = $heldCost;
                $openingCaptured = true;
            }

            $amount = (float) $tx->amount;
            $price = (float) $tx->price_jpy;
            $fee = (float) $tx->fee_jpy;
            $type = $tx->type;

            if ($type === Transaction::TYPE_BUY) {
                $heldAmount += $amount;
                $heldCost += $amount * $price + $fee;
                $avgPrice = $heldAmount > 0 ? $heldCost / $heldAmount : 0.0;

                if ($txYear === $year) {
                    $buyCountInYear++;
                    $buyAmountInYear += $amount;
                    $buyCostInYear += $amount * $price + $fee;
                }
            } elseif ($type === Transaction::TYPE_TRANSFER_IN) {
                $heldAmount += $amount;
                $heldCost += $amount * $price;
                $avgPrice = $heldAmount > 0 ? $heldCost / $heldAmount : 0.0;
            } elseif ($type === Transaction::TYPE_SELL) {
                $soldAmount = min($amount, $heldAmount);
                $costOut = $avgPrice * $soldAmount;

                $heldAmount -= $soldAmount;
                $heldCost = max(0.0, $heldCost - $costOut);

                if ($txYear === $year) {
                    $gross = $amount * $price;
                    $proceeds += $gross;
                    $costOfSold += $costOut;
                    $sellFees += $fee;
                    $sellCount++;

                    $lots[] = [
                        'executed_at' => $tx->executed_at?->toIso8601String(),
                        'amount' => $amount,
                        'price_jpy' => $price,
                        'proceeds' => $gross,
                        'cost_basis_unit' => $avgPrice,
                        'cost_basis' => $costOut,
                        'fee_jpy' => $fee,
                        'realized_gain' => $gross - $costOut - $fee,
                    ];
                }
            } elseif ($type === Transaction::TYPE_TRANSFER_OUT) {
                $soldAmount = min($amount, $heldAmount);
                $costOut = $avgPrice * $soldAmount;
                $heldAmount -= $soldAmount;
                $heldCost = max(0.0, $heldCost - $costOut);
            }
        }

        if (! $openingCaptured) {
            $openingAmount = $heldAmount;
            $openingCost = $heldCost;
        }

        $realizedGain = $proceeds - $costOfSold - $sellFees;

        return [
            'method' => self::METHOD_MOVING_AVERAGE,
            'opening_amount' => $openingAmount,
            'opening_cost' => $openingCost,
            'opening_avg_price' => $openingAmount > 0 ? $openingCost / $openingAmount : 0.0,
            'buy_count_in_year' => $buyCountInYear,
            'buy_amount_in_year' => $buyAmountInYear,
            'buy_cost_in_year' => $buyCostInYear,
            'average_cost' => $avgPrice,
            'ending_amount' => $heldAmount,
            'ending_cost' => $heldCost,
            'proceeds' => $proceeds,
            'cost_of_sold' => $costOfSold,
            'sell_fees' => $sellFees,
            'realized_gain' => $realizedGain,
            'sell_count' => $sellCount,
            'lots' => $lots,
        ];
    }

    /**
     * 総平均法による年次実現損益。
     * 平均取得単価 = (期首在庫評価額 + 期中取得価額) / (期首在庫数量 + 期中取得数量)
     *
     * @param  Collection<int, Transaction>  $txs
     * @return array<string, mixed>
     */
    private function calculateTotalAverage(Collection $txs, int $year): array
    {
        $openingAmount = 0.0;
        $openingCost = 0.0;
        $openingAvg = 0.0;
        $runningAmount = 0.0;
        $runningCost = 0.0;

        foreach ($txs as $tx) {
            $txYear = (int) ($tx->executed_at?->format('Y') ?? 0);
            if ($txYear >= $year) {
                break;
            }

            $amount = (float) $tx->amount;
            $price = (float) $tx->price_jpy;
            $fee = (float) $tx->fee_jpy;

            if ($tx->type === Transaction::TYPE_BUY) {
                $runningAmount += $amount;
                $runningCost += $amount * $price + $fee;
            } elseif ($tx->type === Transaction::TYPE_TRANSFER_IN) {
                $runningAmount += $amount;
                $runningCost += $amount * $price;
            } elseif ($tx->type === Transaction::TYPE_SELL || $tx->type === Transaction::TYPE_TRANSFER_OUT) {
                $avg = $runningAmount > 0 ? $runningCost / $runningAmount : 0.0;
                $out = min($amount, $runningAmount);
                $runningAmount -= $out;
                $runningCost = max(0.0, $runningCost - $avg * $out);
            }
        }
        $openingAmount = $runningAmount;
        $openingCost = $runningCost;
        $openingAvg = $openingAmount > 0 ? $openingCost / $openingAmount : 0.0;

        $buyAmountInYear = 0.0;
        $buyCostInYear = 0.0;
        $buyCountInYear = 0;
        $soldAmountInYear = 0.0;
        $proceeds = 0.0;
        $sellFees = 0.0;
        $sellCount = 0;
        $sells = [];
        $endingAmount = $openingAmount;

        foreach ($txs as $tx) {
            $txYear = (int) ($tx->executed_at?->format('Y') ?? 0);
            if ($txYear !== $year) {
                if ($txYear > $year) {
                    break;
                }
                continue;
            }

            $amount = (float) $tx->amount;
            $price = (float) $tx->price_jpy;
            $fee = (float) $tx->fee_jpy;

            if ($tx->type === Transaction::TYPE_BUY) {
                $buyCountInYear++;
                $buyAmountInYear += $amount;
                $buyCostInYear += $amount * $price + $fee;
                $endingAmount += $amount;
            } elseif ($tx->type === Transaction::TYPE_TRANSFER_IN) {
                $buyAmountInYear += $amount;
                $buyCostInYear += $amount * $price;
                $endingAmount += $amount;
            } elseif ($tx->type === Transaction::TYPE_SELL) {
                $soldAmountInYear += $amount;
                $proceeds += $amount * $price;
                $sellFees += $fee;
                $sellCount++;
                $endingAmount -= $amount;
                $sells[] = [
                    'executed_at' => $tx->executed_at?->toIso8601String(),
                    'amount' => $amount,
                    'price_jpy' => $price,
                    'proceeds' => $amount * $price,
                    'fee_jpy' => $fee,
                ];
            } elseif ($tx->type === Transaction::TYPE_TRANSFER_OUT) {
                $endingAmount -= $amount;
            }
        }

        $totalAmount = $openingAmount + $buyAmountInYear;
        $totalCost = $openingCost + $buyCostInYear;
        $averageCost = $totalAmount > 0 ? $totalCost / $totalAmount : 0.0;

        $costOfSold = $averageCost * $soldAmountInYear;
        $realizedGain = $proceeds - $costOfSold - $sellFees;

        $lots = array_map(function ($s) use ($averageCost) {
            return array_merge($s, [
                'cost_basis_unit' => $averageCost,
                'cost_basis' => $averageCost * $s['amount'],
                'realized_gain' => $s['proceeds'] - $averageCost * $s['amount'] - $s['fee_jpy'],
            ]);
        }, $sells);

        return [
            'method' => self::METHOD_TOTAL_AVERAGE,
            'opening_amount' => $openingAmount,
            'opening_cost' => $openingCost,
            'opening_avg_price' => $openingAvg,
            'buy_count_in_year' => $buyCountInYear,
            'buy_amount_in_year' => $buyAmountInYear,
            'buy_cost_in_year' => $buyCostInYear,
            'average_cost' => $averageCost,
            'ending_amount' => $endingAmount,
            'ending_cost' => $averageCost * max($endingAmount, 0),
            'proceeds' => $proceeds,
            'cost_of_sold' => $costOfSold,
            'sell_fees' => $sellFees,
            'realized_gain' => $realizedGain,
            'sell_count' => $sellCount,
            'lots' => $lots,
        ];
    }

    /**
     * ユーザーが取引実績を持つ年のリストを返す（降順）。
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, int>
     */
    public function availableYears(Collection $transactions): array
    {
        $years = $transactions
            ->map(fn ($tx) => (int) ($tx->executed_at?->format('Y') ?? 0))
            ->filter(fn ($y) => $y > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if ($years === []) {
            return [(int) now()->format('Y')];
        }

        return $years;
    }
}
