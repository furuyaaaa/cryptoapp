<?php

namespace App\Services\Exchanges;

use App\Models\Asset;
use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\DailyQuoteRateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BitgetExecutionMapper
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

        $executedAt = Carbon::createFromTimestampMs((int) ($fill['cTime'] ?? 0));
        $usdtJpy = $this->rates->usdtJpyRateForDate($executedAt);

        $side = Str::lower((string) ($fill['side'] ?? ''));
        $type = match ($side) {
            'buy' => Transaction::TYPE_BUY,
            'sell' => Transaction::TYPE_SELL,
            default => throw new InvalidArgumentException('Unsupported Bitget side: '.$side),
        };

        $note = 'Imported from Bitget';
        $feeJpy = $this->feeJpy($fill, $usdtJpy);
        $feeCoin = strtoupper((string) data_get($fill, 'feeDetail.feeCoin', ''));
        $fee = $this->absoluteDecimalString((string) data_get($fill, 'feeDetail.totalFee', '0'));
        if ((float) $fee > 0 && $feeCoin !== '' && $feeCoin !== 'USDT') {
            $note .= ' / 手数料: '.$fee.' '.$feeCoin;
        }

        return [
            'portfolio_id' => $connection->portfolio_id,
            'asset_id' => $asset->id,
            'exchange_id' => $connection->exchange_id,
            'type' => $type,
            'amount' => abs((float) ($fill['size'] ?? 0)),
            'price_jpy' => (float) ($fill['priceAvg'] ?? 0) * $usdtJpy,
            'fee_jpy' => $feeJpy,
            'executed_at' => $executedAt,
            'note' => $note,
            'external_source' => 'bitget:fills',
            'external_id' => $symbol.':'.(string) ($fill['tradeId'] ?? ''),
            'synced_at' => now(),
        ];
    }

    private function assetSymbolFromSymbol(string $symbol): string
    {
        if (! str_ends_with($symbol, 'USDT')) {
            throw new InvalidArgumentException('Only USDT spot symbols are supported initially.');
        }

        return substr($symbol, 0, -4);
    }

    /**
     * @param  array<string, mixed>  $fill
     */
    private function feeJpy(array $fill, float $usdtJpy): float
    {
        $feeCoin = strtoupper((string) data_get($fill, 'feeDetail.feeCoin', ''));

        return $feeCoin === 'USDT'
            ? abs((float) data_get($fill, 'feeDetail.totalFee', 0)) * $usdtJpy
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
