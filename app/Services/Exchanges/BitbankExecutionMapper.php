<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BitbankExecutionMapper
{
    /**
     * @param  array<string, mixed>  $trade
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $trade, ?string $pair = null): array
    {
        $pair ??= (string) ($trade['pair'] ?? $connection->product_code);
        $symbol = $this->symbolFromPair($pair);
        $asset = Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol],
        );

        $side = strtolower((string) ($trade['side'] ?? ''));
        $type = match ($side) {
            'buy' => Transaction::TYPE_BUY,
            'sell' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException("Unsupported bitbank side: {$side}"),
        };

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($trade['amount'] ?? 0)),
            'price_jpy' => (float) ($trade['price'] ?? 0),
            'fee_jpy' => abs((float) ($trade['fee_amount_quote'] ?? 0)),
            'executed_at' => Carbon::createFromTimestampMs((int) ($trade['executed_at'] ?? 0)),
            'note' => 'Imported from bitbank',
            'external_source' => 'bitbank:trade_history',
            'external_id' => $pair.':'.(string) $trade['trade_id'],
            'synced_at' => now(),
        ];
    }

    private function symbolFromPair(string $pair): string
    {
        if (! str_ends_with($pair, '_jpy')) {
            throw new InvalidArgumentException('Only JPY spot pairs are supported initially.');
        }

        return strtoupper(str_replace('_jpy', '', $pair));
    }
}
