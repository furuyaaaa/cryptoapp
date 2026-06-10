<?php

namespace App\Services;

use App\Models\DailyQuoteRate;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class DailyQuoteRateService
{
    public function __construct(private readonly CoinGeckoService $coinGecko) {}

    public function usdtJpyRateForDate(CarbonInterface $date): float
    {
        return $this->rateForDate('USDT', 'JPY', $date);
    }

    public function rateForDate(string $baseCurrency, string $quoteCurrency, CarbonInterface $date): float
    {
        $baseCurrency = strtoupper($baseCurrency);
        $quoteCurrency = strtoupper($quoteCurrency);
        $rateDate = $date->toDateString();

        $existing = DailyQuoteRate::query()
            ->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->whereDate('rate_date', $rateDate)
            ->first();

        if ($existing !== null) {
            return (float) $existing->rate;
        }

        if ($baseCurrency !== 'USDT' || $quoteCurrency !== 'JPY') {
            throw new InvalidArgumentException("Unsupported quote rate: {$baseCurrency}/{$quoteCurrency}");
        }

        $rate = $this->coinGecko->fetchHistoricalJpyPrice('tether', $date);

        DailyQuoteRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
                'rate_date' => $rateDate,
            ],
            [
                'rate' => $rate,
                'source' => 'coingecko:tether:history',
                'fetched_at' => now(),
            ],
        );

        return $rate;
    }
}
