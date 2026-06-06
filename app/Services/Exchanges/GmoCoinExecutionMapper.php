<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class GmoCoinExecutionMapper
{
    /**
     * @param  array<string, mixed>  $execution
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $execution): array
    {
        $symbol = strtoupper((string) ($execution['symbol'] ?? $connection->product_code));

        if (str_contains($symbol, '_')) {
            throw new InvalidArgumentException('Only GMO Coin spot symbols are supported initially.');
        }

        $asset = Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol],
        );

        $side = strtoupper((string) ($execution['side'] ?? ''));
        $type = match ($side) {
            'BUY' => Transaction::TYPE_BUY,
            'SELL' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException("Unsupported GMO Coin side: {$side}"),
        };

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($execution['size'] ?? 0)),
            'price_jpy' => (float) ($execution['price'] ?? 0),
            'fee_jpy' => abs((float) ($execution['fee'] ?? 0)),
            'executed_at' => Carbon::parse((string) $execution['timestamp']),
            'note' => 'Imported from GMO Coin',
            'external_source' => 'gmo_coin:latest_executions',
            'external_id' => $symbol.':'.(string) $execution['executionId'],
            'synced_at' => now(),
        ];
    }
}
