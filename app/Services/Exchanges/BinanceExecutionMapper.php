<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BinanceExecutionMapper
{
    /**
     * @param  array<string, mixed>  $trade
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $trade): array
    {
        $symbol = strtoupper((string) ($trade['symbol'] ?? $connection->product_code));
        $assetSymbol = $this->assetSymbolFromSymbol($symbol);
        $asset = Asset::firstOrCreate(
            ['symbol' => $assetSymbol],
            ['name' => $assetSymbol],
        );

        $type = ((bool) ($trade['isBuyer'] ?? false))
            ? Transaction::TYPE_BUY
            : Transaction::TYPE_SELL;

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($trade['qty'] ?? 0)),
            'price_jpy' => (float) ($trade['price'] ?? 0),
            'fee_jpy' => $this->feeJpy($trade),
            'executed_at' => Carbon::createFromTimestampMs((int) ($trade['time'] ?? 0)),
            'note' => 'Imported from Binance Japan',
            'external_source' => 'binance:my_trades',
            'external_id' => $symbol.':'.(string) $trade['id'],
            'synced_at' => now(),
        ];
    }

    private function assetSymbolFromSymbol(string $symbol): string
    {
        if (! str_ends_with($symbol, 'JPY')) {
            throw new InvalidArgumentException('Only JPY symbols are supported initially.');
        }

        return substr($symbol, 0, -3);
    }

    /**
     * @param  array<string, mixed>  $trade
     */
    private function feeJpy(array $trade): float
    {
        $commissionAsset = strtoupper((string) ($trade['commissionAsset'] ?? ''));

        return $commissionAsset === 'JPY'
            ? abs((float) ($trade['commission'] ?? 0))
            : 0.0;
    }
}
