<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\DailyQuoteRateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class KuCoinExecutionMapper
{
    public function __construct(private readonly DailyQuoteRateService $rates) {}

    /**
     * @param  array<string, mixed>  $fill
     * @return array<string, mixed>
     */
    public function map(ExchangeConnection $connection, array $fill): array
    {
        $symbol = strtoupper((string) ($fill['symbol'] ?? $connection->product_code));
        $assetSymbol = $this->assetSymbolFromSymbol($symbol);
        $asset = Asset::firstOrCreate(
            ['symbol' => $assetSymbol],
            ['name' => $assetSymbol],
        );

        $executedAt = Carbon::createFromTimestampMs((int) ($fill['createdAt'] ?? 0));
        $usdtJpy = $this->rates->usdtJpyRateForDate($executedAt);

        $side = Str::lower((string) ($fill['side'] ?? ''));
        $type = match ($side) {
            'buy' => Transaction::TYPE_BUY,
            'sell' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException('Unsupported KuCoin side: '.$side),
        };

        $note = 'Imported from KuCoin';
        $feeJpy = $this->feeJpy($fill, $usdtJpy);
        $feeCurrency = strtoupper((string) ($fill['feeCurrency'] ?? ''));
        $fee = $this->absoluteDecimalString((string) ($fill['fee'] ?? '0'));
        if ((float) $fee > 0 && $feeCurrency !== '' && $feeCurrency !== 'USDT') {
            $note .= ' / 手数料: '.$fee.' '.$feeCurrency;
        }

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($fill['size'] ?? 0)),
            'price_jpy' => (float) ($fill['price'] ?? 0) * $usdtJpy,
            'fee_jpy' => $feeJpy,
            'executed_at' => $executedAt,
            'note' => $note,
            'external_source' => 'kucoin:fills',
            'external_id' => $symbol.':'.(string) ($fill['tradeId'] ?? $fill['id'] ?? ''),
            'synced_at' => now(),
        ];
    }

    private function assetSymbolFromSymbol(string $symbol): string
    {
        if (! str_ends_with($symbol, '-USDT')) {
            throw new InvalidArgumentException('Only USDT spot symbols are supported initially.');
        }

        return substr($symbol, 0, -5);
    }

    /**
     * @param  array<string, mixed>  $fill
     */
    private function feeJpy(array $fill, float $usdtJpy): float
    {
        $feeCurrency = strtoupper((string) ($fill['feeCurrency'] ?? ''));

        return $feeCurrency === 'USDT'
            ? abs((float) ($fill['fee'] ?? 0)) * $usdtJpy
            : 0.0;
    }

    private function absoluteDecimalString(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '0';
        }

        $negative = str_starts_with($value, '-');
        $normalized = $negative ? substr($value, 1) : $value;

        return $normalized !== '' ? $normalized : '0';
    }
}
