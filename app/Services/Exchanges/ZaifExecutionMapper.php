<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ZaifExecutionMapper
{
    /**
     * @param  array<string, mixed>  $trade
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $trade, string $tradeId): array
    {
        $pair = (string) ($trade['currency_pair'] ?? $connection->product_code);
        $symbol = $this->symbolFromPair($pair);
        $asset = Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol],
        );

        $side = (string) ($trade['your_action'] ?? $trade['action'] ?? '');
        $type = match ($side) {
            'bid' => Transaction::TYPE_BUY,
            'ask' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException("Unsupported Zaif side: {$side}"),
        };

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($trade['amount'] ?? 0)),
            'price_jpy' => (float) ($trade['price'] ?? 0),
            'fee_jpy' => abs((float) ($trade['fee_amount'] ?? $trade['fee'] ?? 0)),
            'executed_at' => Carbon::createFromTimestamp((int) ($trade['timestamp'] ?? 0)),
            'note' => 'Imported from Zaif',
            'external_source' => 'zaif:trade_history',
            'external_id' => $pair.':'.$tradeId,
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
}
