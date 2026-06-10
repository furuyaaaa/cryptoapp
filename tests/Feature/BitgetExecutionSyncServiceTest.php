<?php

use App\Models\DailyQuoteRate;
use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\BitgetExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('Bitget sync imports USDT spot fills using daily USDT JPY rate and skips duplicates', function () {
    Carbon::setTestNow('2026-01-02 12:00:00');

    $oldFillTime = Carbon::parse('2025-12-31 12:00:00')->getTimestampMs();
    $newFillTime = Carbon::parse('2026-01-01 12:00:00')->getTimestampMs();

    Http::fake([
        'https://api.bitget.com/api/v2/spot/public/symbols' => Http::response([
            'code' => '00000',
            'msg' => 'success',
            'data' => [
                [
                    'symbol' => 'BTCUSDT',
                    'baseCoin' => 'BTC',
                    'quoteCoin' => 'USDT',
                    'status' => 'online',
                ],
                [
                    'symbol' => 'ETHBTC',
                    'baseCoin' => 'ETH',
                    'quoteCoin' => 'BTC',
                    'status' => 'online',
                ],
            ],
        ], 200),
        'https://api.bitget.com/api/v2/spot/trade/fills*' => Http::response([
            'code' => '00000',
            'msg' => 'success',
            'data' => [
                [
                    'symbol' => 'BTCUSDT',
                    'orderId' => 'order-1',
                    'tradeId' => 'trade-old',
                    'side' => 'buy',
                    'priceAvg' => '59000',
                    'size' => '0.01',
                    'amount' => '590',
                    'feeDetail' => [
                        'feeCoin' => 'USDT',
                        'totalFee' => '-1',
                    ],
                    'cTime' => (string) $oldFillTime,
                ],
                [
                    'symbol' => 'BTCUSDT',
                    'orderId' => 'order-2',
                    'tradeId' => 'trade-new',
                    'side' => 'sell',
                    'priceAvg' => '60000',
                    'size' => '0.02',
                    'amount' => '1200',
                    'feeDetail' => [
                        'feeCoin' => 'BTC',
                        'totalFee' => '-0.00001',
                    ],
                    'cTime' => (string) $newFillTime,
                ],
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
    $exchange = Exchange::create(['code' => 'bitget', 'name' => 'Bitget', 'country' => null]);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'Bitget 全USDT建て現物',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'api_passphrase' => 'passphrase',
        'product_code' => BitgetExecutionSyncService::ALL_USDT_SYMBOLS,
        'sync_start_at' => '2026-01-01 00:00:00',
        'is_active' => true,
    ]);

    $service = app(BitgetExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('bitget:fills')
        ->and($transaction->external_id)->toBe('BTCUSDT:trade-new')
        ->and($transaction->type)->toBe(Transaction::TYPE_SELL)
        ->and((float) $transaction->amount)->toBe(0.02)
        ->and((float) $transaction->price_jpy)->toBe(9000000.0)
        ->and((float) $transaction->fee_jpy)->toBe(0.0)
        ->and($transaction->note)->toBe('Imported from Bitget / 手数料: 0.00001 BTC');

    $rate = DailyQuoteRate::first();
    expect($rate->base_currency)->toBe('USDT')
        ->and($rate->quote_currency)->toBe('JPY')
        ->and($rate->rate_date->toDateString())->toBe('2026-01-01')
        ->and((float) $rate->rate)->toBe(150.0);

    Carbon::setTestNow();
});
