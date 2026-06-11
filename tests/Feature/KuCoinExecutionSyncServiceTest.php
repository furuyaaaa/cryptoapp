<?php

use App\Models\DailyQuoteRate;
use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\KuCoinExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('KuCoin sync imports USDT spot fills using daily USDT JPY rate and skips duplicates', function () {
    Carbon::setTestNow('2026-01-02 12:00:00');

    $oldFillTime = Carbon::parse('2025-12-31 12:00:00')->getTimestampMs();
    $newFillTime = Carbon::parse('2026-01-01 12:00:00')->getTimestampMs();

    Http::fake([
        'https://api.kucoin.com/api/v2/symbols' => Http::response([
            'code' => '200000',
            'data' => [
                [
                    'symbol' => 'BTC-USDT',
                    'baseCurrency' => 'BTC',
                    'quoteCurrency' => 'USDT',
                    'enableTrading' => true,
                ],
                [
                    'symbol' => 'ETH-BTC',
                    'baseCurrency' => 'ETH',
                    'quoteCurrency' => 'BTC',
                    'enableTrading' => true,
                ],
            ],
        ], 200),
        'https://api.kucoin.com/api/v1/hf/fills*' => Http::response([
            'code' => '200000',
            'data' => [
                'items' => [
                    [
                        'id' => 'fill-old',
                        'orderId' => 'order-1',
                        'tradeId' => 'trade-old',
                        'symbol' => 'BTC-USDT',
                        'side' => 'buy',
                        'price' => '59000',
                        'size' => '0.01',
                        'funds' => '590',
                        'fee' => '1',
                        'feeCurrency' => 'USDT',
                        'createdAt' => $oldFillTime,
                    ],
                    [
                        'id' => 'fill-new',
                        'orderId' => 'order-2',
                        'tradeId' => 'trade-new',
                        'symbol' => 'BTC-USDT',
                        'side' => 'sell',
                        'price' => '60000',
                        'size' => '0.02',
                        'funds' => '1200',
                        'fee' => '0.00001',
                        'feeCurrency' => 'BTC',
                        'createdAt' => $newFillTime,
                    ],
                ],
                'lastId' => null,
            ],
        ], 200),
        'https://api.coingecko.com/api/v3/coins/tether/history*' => Http::response([
            'market_data' => [
                'current_price' => [
                    'jpy' => 150,
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
    $exchange = Exchange::create(['code' => 'kucoin', 'name' => 'KuCoin', 'country' => null]);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'KuCoin 全USDT建て現物',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'api_passphrase' => 'passphrase',
        'product_code' => KuCoinExecutionSyncService::ALL_USDT_SYMBOLS,
        'sync_start_at' => '2026-01-01 00:00:00',
        'is_active' => true,
    ]);

    $service = app(KuCoinExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('kucoin:fills')
        ->and($transaction->external_id)->toBe('BTC-USDT:trade-new')
        ->and($transaction->type)->toBe(Transaction::TYPE_SELL)
        ->and((float) $transaction->amount)->toBe(0.02)
        ->and((float) $transaction->price_jpy)->toBe(9000000.0)
        ->and((float) $transaction->fee_jpy)->toBe(0.0)
        ->and($transaction->note)->toBe('Imported from KuCoin / 手数料: 0.00001 BTC');

    $rate = DailyQuoteRate::first();
    expect($rate->base_currency)->toBe('USDT')
        ->and($rate->quote_currency)->toBe('JPY')
        ->and($rate->rate_date->toDateString())->toBe('2026-01-01')
        ->and((float) $rate->rate)->toBe(150.0);

    Carbon::setTestNow();
});
