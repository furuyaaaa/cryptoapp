<?php

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\BinanceExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('Binance sync imports JPY spot trades and skips old executions and duplicates', function () {
    Carbon::setTestNow('2026-01-01 12:00:00');

    Http::fake([
        'https://api.binance.com/api/v3/exchangeInfo' => Http::response([
            'symbols' => [
                [
                    'symbol' => 'BTCJPY',
                    'status' => 'TRADING',
                    'quoteAsset' => 'JPY',
                    'isSpotTradingAllowed' => true,
                ],
                [
                    'symbol' => 'ETHBTC',
                    'status' => 'TRADING',
                    'quoteAsset' => 'BTC',
                    'isSpotTradingAllowed' => true,
                ],
            ],
        ], 200),
        'https://api.binance.com/api/v3/myTrades*' => Http::response([
            [
                'id' => 181,
                'isBuyer' => true,
                'qty' => '0.02000000',
                'price' => '9000000.00000000',
                'commission' => '10.00000000',
                'commissionAsset' => 'JPY',
                'time' => 1767139200000,
            ],
            [
                'id' => 182,
                'isBuyer' => false,
                'qty' => '0.01000000',
                'price' => '10000000.00000000',
                'commission' => '0.00001000',
                'commissionAsset' => 'BTC',
                'time' => 1767225600000,
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
    $exchange = Exchange::create(['code' => 'binance', 'name' => 'Binance Japan', 'country' => 'JP']);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'Binance Japan 全JPY建て現物',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'product_code' => BinanceExecutionSyncService::ALL_JPY_SYMBOLS,
        'sync_start_at' => '2026-01-01 00:00:00',
        'is_active' => true,
    ]);

    $service = app(BinanceExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('binance:my_trades')
        ->and($transaction->external_id)->toBe('BTCJPY:182')
        ->and($transaction->type)->toBe(Transaction::TYPE_SELL)
        ->and((float) $transaction->amount)->toBe(0.01)
        ->and((float) $transaction->price_jpy)->toBe(10000000.0)
        ->and((float) $transaction->fee_jpy)->toBe(0.0);

    Carbon::setTestNow();
});
