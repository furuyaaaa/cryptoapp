<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BitFlyerExecutionMapper
{
    /**
     * @param  array<string, mixed>  $execution
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $execution): array
    {
        $symbol = $this->symbolFromProductCode($connection->product_code);
        $asset = Asset::firstWhere('symbol', $symbol);

        if (! $asset) {
            throw new InvalidArgumentException("Asset {$symbol} is not registered.");
        }

        $side = strtoupper((string) ($execution['side'] ?? ''));
        $type = match ($side) {
            'BUY' => Transaction::TYPE_BUY,
            'SELL' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException("Unsupported bitFlyer side: {$side}"),
        };

        $price = (float) ($execution['price'] ?? 0);
        $commission = abs((float) ($execution['commission'] ?? 0));

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($execution['size'] ?? 0)),
            'price_jpy' => $price,
            'fee_jpy' => $commission * $price,
            'executed_at' => Carbon::parse((string) $execution['exec_date']),
            'note' => 'Imported from bitFlyer',
            'external_source' => 'bitflyer:getexecutions',
            'external_id' => (string) $execution['id'],
            'synced_at' => now(),
        ];
    }

    private function symbolFromProductCode(string $productCode): string
    {
        if (! str_ends_with($productCode, '_JPY')) {
            throw new InvalidArgumentException('Only JPY spot product codes are supported initially.');
        }

        return strtoupper(str_replace('_JPY', '', $productCode));
    }
}
