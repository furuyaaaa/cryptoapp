<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CoincheckExecutionMapper
{
    /**
     * @param  array<string, mixed>  $transaction
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $transaction): array
    {
        $pair = (string) ($transaction['pair'] ?? $connection->product_code);
        $symbol = $this->symbolFromPair($pair);
        $asset = Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol],
        );

        $side = strtolower((string) ($transaction['side'] ?? ''));
        $type = match ($side) {
            'buy' => Transaction::TYPE_BUY,
            'sell' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException("Unsupported Coincheck side: {$side}"),
        };

        $funds = (array) ($transaction['funds'] ?? []);
        $amount = abs((float) ($funds[strtolower($symbol)] ?? 0));
        if ($amount === 0.0) {
            $amount = abs((float) ($transaction['amount'] ?? 0));
        }

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => $amount,
            'price_jpy' => (float) ($transaction['rate'] ?? 0),
            'fee_jpy' => $this->feeJpy($transaction),
            'executed_at' => Carbon::parse((string) $transaction['created_at']),
            'note' => 'Imported from Coincheck',
            'external_source' => 'coincheck:transactions',
            'external_id' => $pair.':'.(string) $transaction['id'],
            'synced_at' => now(),
        ];
    }

    private function symbolFromPair(string $pair): string
    {
        if (! str_ends_with($pair, '_jpy')) {
            throw new InvalidArgumentException('Only JPY pairs are supported initially.');
        }

        return strtoupper(str_replace('_jpy', '', $pair));
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function feeJpy(array $transaction): float
    {
        $currency = strtoupper((string) ($transaction['fee_currency'] ?? ''));
        $fee = abs((float) ($transaction['fee'] ?? 0));

        return $currency === 'JPY' ? $fee : 0.0;
    }
}
