<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\DailyQuoteRateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CoinbaseExecutionMapper
{
    public function __construct(private readonly DailyQuoteRateService $rates) {}

    /**
     * @param  array<string, mixed>  $fill
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $fill): array
    {
        $productId = strtoupper((string) ($fill['product_id'] ?? $connection->product_code));
        [$assetSymbol, $quoteCurrency] = $this->symbolsFromProductId($productId);
        $asset = Asset::firstOrCreate(
            ['symbol' => $assetSymbol],
            ['name' => $assetSymbol],
        );

        $executedAt = Carbon::parse((string) ($fill['trade_time'] ?? $fill['sequence_timestamp'] ?? now()->toIso8601String()));
        $stableJpy = $this->rates->usdtJpyRateForDate($executedAt);

        $side = Str::lower((string) ($fill['side'] ?? ''));
        $type = match ($side) {
            'buy' => Transaction::TYPE_BUY,
            'sell' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException('Unsupported Coinbase side: '.$side),
        };

        $note = 'Imported from Coinbase';
        if ($quoteCurrency !== 'USDT') {
            $note .= ' / '.$quoteCurrency.'建てをUSDT/JPY日次レートで換算';
        }

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($fill['size'] ?? 0)),
            'price_jpy' => (float) ($fill['price'] ?? 0) * $stableJpy,
            'fee_jpy' => abs((float) ($fill['commission'] ?? data_get($fill, 'commission_detail_total.total_commission', 0))) * $stableJpy,
            'executed_at' => $executedAt,
            'note' => $note,
            'external_source' => 'coinbase:fills',
            'external_id' => $productId.':'.(string) ($fill['entry_id'] ?? $fill['trade_id'] ?? ''),
            'synced_at' => now(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function symbolsFromProductId(string $productId): array
    {
        foreach (['-USDT', '-USDC', '-USD'] as $suffix) {
            if (str_ends_with($productId, $suffix)) {
                return [substr($productId, 0, -strlen($suffix)), substr($suffix, 1)];
            }
        }

        throw new InvalidArgumentException('Only Coinbase USD/USDC/USDT spot products are supported initially.');
    }
}
