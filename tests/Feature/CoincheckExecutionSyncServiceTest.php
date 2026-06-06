<?php

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\CoincheckExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('Coincheck sync imports JPY pair transactions and skips duplicates', function () {
    Http::fake([
        'https://coincheck.com/api/exchange/orders/transactions_pagination*' => Http::response([
            'success' => true,
            'pagination' => ['limit' => 100, 'order' => 'desc'],
            'data' => [
                [
                    'id' => 38,
                    'order_id' => 49,
                    'created_at' => '2015-11-18T07:02:21.000Z',
                    'funds' => ['btc' => '0.1', 'jpy' => '-4096.135'],
                    'pair' => 'btc_jpy',
                    'rate' => '40900.0',
                    'fee_currency' => 'JPY',
                    'fee' => '6.135',
                    'liquidity' => 'T',
                    'side' => 'buy',
                ],
                [
                    'id' => 39,
                    'created_at' => '2015-11-18T07:02:21.000Z',
                    'funds' => ['btc' => '0.1', 'eth' => '-1.5'],
                    'pair' => 'btc_eth',
                    'rate' => '15.0',
                    'fee_currency' => 'ETH',
                    'fee' => '0.01',
                    'side' => 'buy',
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $exchange = Exchange::create(['code' => 'coincheck', 'name' => 'Coincheck', 'country' => 'JP']);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'Coincheck 全JPY建て取引所ペア',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'product_code' => CoincheckExecutionSyncService::ALL_JPY_PAIRS,
        'is_active' => true,
    ]);

    $service = app(CoincheckExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('coincheck:transactions')
        ->and($transaction->external_id)->toBe('btc_jpy:38')
        ->and((float) $transaction->amount)->toBe(0.1)
        ->and((float) $transaction->price_jpy)->toBe(40900.0)
        ->and((float) $transaction->fee_jpy)->toBe(6.135);
});
