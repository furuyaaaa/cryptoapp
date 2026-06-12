<?php

use App\Models\DailyQuoteRate;
use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\CoinbaseExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

if (! function_exists('coinbaseTestPrivateKey')) {
    function coinbaseTestPrivateKey(): string
    {
        return <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIN0ekMQxWGuh23oKDf37MMxwNw12vL1UAxEUmfOxH1EUoAoGCCqGSM49
AwEHoUQDQgAEil5n06Q+bAtqcTvj4v56s5ReZ5H/LfMaCKG2Rq9XoR0uhNxZS37B
Rp2EzUfXijbgnDPI0IgccBatG7eJXZdTNw==
-----END EC PRIVATE KEY-----
PEM;
    }
}

test('Coinbase sync imports stable quote spot fills using daily USDT JPY rate and skips duplicates', function () {
    Carbon::setTestNow('2026-01-02 12:00:00');

    Http::fake([
        'https://api.coinbase.com/api/v3/brokerage/products*' => Http::response([
            'products' => [
                [
                    'product_id' => 'BTC-USD',
                    'quote_currency_id' => 'USD',
                    'is_disabled' => false,
                    'trading_disabled' => false,
                ],
                [
                    'product_id' => 'ETH-BTC',
                    'quote_currency_id' => 'BTC',
                    'is_disabled' => false,
                    'trading_disabled' => false,
                ],
            ],
        ], 200),
        'https://api.coinbase.com/api/v3/brokerage/orders/historical/fills*' => Http::response([
            'fills' => [
                [
                    'entry_id' => 'fill-old',
                    'trade_id' => 'trade-old',
                    'order_id' => 'order-1',
                    'trade_time' => '2025-12-31T12:00:00.000Z',
                    'price' => '59000',
                    'size' => '0.01',
                    'commission' => '1.25',
                    'product_id' => 'BTC-USD',
                    'side' => 'BUY',
                ],
                [
                    'entry_id' => 'fill-new',
                    'trade_id' => 'trade-new',
                    'order_id' => 'order-2',
                    'trade_time' => '2026-01-01T12:00:00.000Z',
                    'price' => '60000',
                    'size' => '0.02',
                    'commission' => '2',
                    'product_id' => 'BTC-USD',
                    'side' => 'SELL',
                ],
            ],
            'cursor' => null,
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
    $exchange = Exchange::create(['code' => 'coinbase', 'name' => 'Coinbase', 'country' => 'US']);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'Coinbase 全USD/USDC/USDT建て現物',
        'api_key' => 'organizations/org/apiKeys/key',
        'api_secret' => coinbaseTestPrivateKey(),
        'product_code' => CoinbaseExecutionSyncService::ALL_STABLE_QUOTE_PRODUCTS,
        'sync_start_at' => '2026-01-01 00:00:00',
        'is_active' => true,
    ]);

    $service = app(CoinbaseExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('coinbase:fills')
        ->and($transaction->external_id)->toBe('BTC-USD:fill-new')
        ->and($transaction->type)->toBe(Transaction::TYPE_SELL)
        ->and((float) $transaction->amount)->toBe(0.02)
        ->and((float) $transaction->price_jpy)->toBe(9000000.0)
        ->and((float) $transaction->fee_jpy)->toBe(300.0)
        ->and($transaction->note)->toBe('Imported from Coinbase / USD建てをUSDT/JPY日次レートで換算');

    $rate = DailyQuoteRate::first();
    expect($rate->base_currency)->toBe('USDT')
        ->and($rate->quote_currency)->toBe('JPY')
        ->and($rate->rate_date->toDateString())->toBe('2026-01-01')
        ->and((float) $rate->rate)->toBe(150.0);

    Carbon::setTestNow();
});
