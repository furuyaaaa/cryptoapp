<?php

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\BitbankExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('bitbank sync imports JPY spot trades and skips duplicates', function () {
    Http::fake([
        'https://api.bitbank.cc/v1/spot/pairs' => Http::response([
            'success' => 1,
            'data' => [
                'pairs' => [
                    ['name' => 'btc_jpy', 'quote_asset' => 'jpy', 'is_enabled' => true, 'stop_order' => false],
                    ['name' => 'eth_btc', 'quote_asset' => 'btc', 'is_enabled' => true, 'stop_order' => false],
                    ['name' => 'xrp_jpy', 'quote_asset' => 'jpy', 'is_enabled' => false, 'stop_order' => false],
                ],
            ],
        ], 200),
        'https://api.bitbank.cc/v1/user/spot/trade_history*' => Http::response([
            'success' => 1,
            'data' => [
                'trades' => [
                    [
                        'trade_id' => 123,
                        'pair' => 'btc_jpy',
                        'side' => 'buy',
                        'amount' => '0.01',
                        'price' => '10000000',
                        'fee_amount_quote' => '15',
                        'executed_at' => 1767225600000,
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
    $exchange = Exchange::create(['code' => 'bitbank', 'name' => 'bitbank', 'country' => 'JP']);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'bitbank 全JPY建て現物',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'product_code' => BitbankExecutionSyncService::ALL_JPY_PAIRS,
        'is_active' => true,
    ]);

    $service = app(BitbankExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 1, 'imported' => 1, 'skipped' => 0]);
    expect($second)->toMatchArray(['fetched' => 1, 'imported' => 0, 'skipped' => 1]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('bitbank:trade_history')
        ->and($transaction->external_id)->toBe('btc_jpy:123')
        ->and((float) $transaction->amount)->toBe(0.01)
        ->and((float) $transaction->price_jpy)->toBe(10000000.0)
        ->and((float) $transaction->fee_jpy)->toBe(15.0);
});
